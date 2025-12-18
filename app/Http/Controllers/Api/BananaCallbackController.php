<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageGenerationRequest;
use App\Models\Post;
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

            $target = null;
            if ($requestRecord->relationLoaded('targetable')) {
                $target = $requestRecord->targetable;
            } else {
                $target = $requestRecord->targetable; // triggers lazy load
            }

            if (! $target && $requestRecord->post_id) {
                // Backward compatibility with legacy post_id column
                $target = Post::query()->find($requestRecord->post_id);
            }

            if ($target instanceof \App\Models\Post) {
                $directory = 'posts';
                // Use attribute from metadata if available, otherwise default to cover_image
                $attribute = $attribute ?? 'cover_image';
                $baseName = \Illuminate\Support\Str::slug($target->title ?? 'post').'-'.$target->id;
            } elseif ($target instanceof \App\Models\Author) {
                $directory = 'authors';
                $attribute = $attribute ?? 'image';
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
                    $target->update([$attribute => $publicUrl]);
                }
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
     * Update a nested attribute in the model (e.g., "content.0.data.url").
     */
    private function updateNestedAttribute($model, string $path, $value): void
    {
        $parts = explode('.', $path);
        $firstKey = array_shift($parts);

        // Get the current value of the first key
        $data = $model->{$firstKey};

        if (! is_array($data)) {
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
                    return;
                }
                $current = &$current[$part];
            }
        }

        // Save the updated data back to the model
        $model->update([$firstKey => $data]);
    }
}
