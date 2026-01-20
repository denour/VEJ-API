<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageGenerationRequest;
use App\Models\Post;
use App\Models\PostBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BananaCallbackController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Try to support multiple payload shapes from Nano Banana
        $taskId = data_get($payload, 'taskId')
            ?? data_get($payload, 'data.taskId')
            ?? data_get($payload, 'data.id');

        $imageUrl = data_get($payload, 'imageUrl')
            ?? data_get($payload, 'resultImageUrl')
            ?? data_get($payload, 'data.info.resultImageUrl')
            ?? data_get($payload, 'data.response.resultImageUrl');

        if (empty($taskId)) {
            return response()->json([
                'message' => 'Missing taskId in payload',
            ], 422);
        }

        $requestRecord = ImageGenerationRequest::query()
            ->where('external_id', (string) $taskId)
            ->first();

        if (! $requestRecord) {
            // Accept but nothing to update yet
            return response()->json([
                'message' => 'No matching request found for taskId, ignoring',
            ], 202);
        }

        // Update status early
        $requestRecord->update([
            'status' => 'processing',
            'metadata' => array_merge((array) $requestRecord->metadata, [
                'webhook' => $payload,
            ]),
        ]);

        if (empty($imageUrl)) {
            // Provider might send intermediate webhook without the URL
            return response()->json([
                'message' => 'Accepted - awaiting image URL',
            ], 202);
        }

        try {
            $imageResponse = Http::timeout(120)->get((string) $imageUrl);
            if (! $imageResponse->successful()) {
                $requestRecord->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to download image from provider',
                ]);

                return response()->json([
                    'message' => 'Failed to download image',
                ], 422);
            }

            // Determine target model and storage directory
            $directory = 'misc';
            $attribute = $requestRecord->metadata['attribute'] ?? null;
            $baseName = uniqid('', true);

            // Load target model - try multiple approaches for robustness
            $target = null;

            // First, try the polymorphic relationship
            if ($requestRecord->targetable_type && $requestRecord->targetable_id) {
                $targetClass = $requestRecord->targetable_type;
                if (class_exists($targetClass)) {
                    $target = $targetClass::find($requestRecord->targetable_id);
                }
            }

            // Fallback to morphTo relationship if direct load failed
            if (! $target) {
                $target = $requestRecord->targetable;
            }

            // Backward compatibility with legacy post_id column
            if (! $target && $requestRecord->post_id) {
                $target = Post::query()->find($requestRecord->post_id);
            }

            Log::info('Target loaded for image generation', [
                'request_id' => $requestRecord->id,
                'external_id' => $requestRecord->external_id,
                'targetable_type' => $requestRecord->targetable_type,
                'targetable_id' => $requestRecord->targetable_id,
                'post_id' => $requestRecord->post_id,
                'target_class' => $target ? get_class($target) : null,
                'target_id' => $target?->id,
                'attribute' => $attribute,
            ]);

            if ($target instanceof \App\Models\PostBlock) {
                $directory = 'posts/blocks';
                $attribute = 'data.url';
                $baseName = 'block-'.$target->id;
            } elseif ($target instanceof \App\Models\Post) {
                $directory = 'posts';
                // Use attribute from metadata if available, otherwise default to cover_image
                $attribute = $attribute ?? 'cover_image';
                $baseName = \Illuminate\Support\Str::slug($target->title ?? 'post').'-'.$target->id;
            } elseif ($target instanceof \App\Models\Author) {
                $directory = 'authors';
                $attribute = $attribute ?? 'avatar_url';
                $baseName = \Illuminate\Support\Str::slug($target->name ?? 'author').'-'.$target->id;
            } elseif ($target instanceof \App\Models\Product) {
                $directory = 'products';
                $attribute = $attribute ?? 'image';
                $baseName = \Illuminate\Support\Str::slug($target->name ?? 'product').'-'.$target->id;
            } elseif ($target instanceof \App\Models\Species) {
                $directory = 'species';
                $attribute = $attribute ?? 'image';
                $baseName = \Illuminate\Support\Str::slug($target->common_name ?? $target->scientific_name ?? 'species').'-'.$target->id;
            }

            $filename = $directory.'/'.$baseName.'.png';
            Storage::disk('s3')->put($filename, $imageResponse->body(), ['visibility' => 'public']);

            $publicUrl = Storage::disk('s3')->url($filename);

            // Update related model attribute when available
            if ($target && $attribute) {
                // Handle nested attributes for content blocks (e.g., "content.0.data.url")
                if (str_contains($attribute, '.')) {
                    $this->updateNestedAttribute($target, $attribute, $publicUrl);
                } else {
                    // For simple attributes, update directly and verify
                    $target->{$attribute} = $publicUrl;
                    $saved = $target->save();

                    Log::info('Simple attribute update', [
                        'model' => get_class($target),
                        'model_id' => $target->id,
                        'attribute' => $attribute,
                        'value' => $publicUrl,
                        'saved' => $saved,
                    ]);

                    // Verify the update persisted
                    $target->refresh();
                    if ($target->{$attribute} !== $publicUrl) {
                        Log::error('Attribute update failed to persist', [
                            'model' => get_class($target),
                            'model_id' => $target->id,
                            'attribute' => $attribute,
                            'expected' => $publicUrl,
                            'actual' => $target->{$attribute},
                        ]);
                    }
                }
            } else {
                Log::warning('Cannot update model attribute - missing target or attribute', [
                    'has_target' => $target !== null,
                    'target_class' => $target ? get_class($target) : null,
                    'attribute' => $attribute,
                    'request_id' => $requestRecord->id,
                ]);
            }

            $requestRecord->update([
                'status' => 'completed',
                'image_path' => $filename,
                'image_url' => $publicUrl,
            ]);

            return response()->json([
                'message' => 'Image stored successfully',
                'url' => $publicUrl,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Banana webhook error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            $requestRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Internal error handling webhook',
            ], 500);
        }
    }

    /**
     * Update a nested attribute in the model (e.g., "data.url" for PostBlock or "content.0.data.url" for legacy Post).
     */
    private function updateNestedAttribute($model, string $path, $value): void
    {
        // Special handling for PostBlock - much simpler!
        if ($model instanceof PostBlock) {
            $parts = explode('.', $path);
            if (count($parts) === 2 && $parts[0] === 'data') {
                $data = $model->data ?? [];
                $data[$parts[1]] = $value;
                $model->data = $data;
                $saved = $model->save();

                Log::info('PostBlock data updated', [
                    'block_id' => $model->id,
                    'key' => $parts[1],
                    'value' => $value,
                    'saved' => $saved,
                ]);

                return;
            }
        }

        // Legacy nested attribute handling for other models
        $parts = explode('.', $path);
        $firstKey = array_shift($parts);

        // Get the current value of the first key (fresh copy)
        $model->refresh();
        $data = $model->{$firstKey};

        if (! is_array($data)) {
            Log::warning('Nested attribute update failed: not an array', [
                'model' => get_class($model),
                'attribute' => $firstKey,
                'path' => $path,
            ]);

            return;
        }

        // Navigate to the nested location and update the value
        $current = &$data;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                // Last part, set the value
                $current[$part] = $value;
            } else {
                // Navigate deeper
                if (! isset($current[$part])) {
                    Log::warning('Nested attribute path not found', [
                        'model' => get_class($model),
                        'path' => $path,
                        'part' => $part,
                        'index' => $i,
                    ]);

                    return;
                }
                $current = &$current[$part];
            }
        }

        // Force Laravel to recognize the change by setting the attribute directly
        $model->{$firstKey} = $data;
        $saved = $model->save();

        Log::info('Nested attribute updated', [
            'model' => get_class($model),
            'model_id' => $model->id,
            'path' => $path,
            'value' => $value,
            'saved' => $saved,
        ]);

        // Verify the update persisted
        $model->refresh();
        $actualValue = data_get($model->{$firstKey}, implode('.', $parts));
        if ($actualValue !== $value) {
            Log::error('Nested attribute update failed to persist', [
                'model' => get_class($model),
                'model_id' => $model->id,
                'path' => $path,
                'expected' => $value,
                'actual' => $actualValue,
            ]);
        }
    }
}
