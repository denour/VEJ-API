<?php

namespace App\Jobs;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Models\ImageGenerationRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PollImageGenerationStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 20; // Max 20 attempts = 10 minutes (30 seconds * 20)

    public int $backoff = 30; // Retry after 30 seconds

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ImageGenerationRequest $imageGenerationRequest
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ImageGeneratorInterface $generator): void
    {
        // Skip if already completed or failed
        if (in_array($this->imageGenerationRequest->status, ['completed', 'failed'])) {
            Log::info('Image generation already completed or failed, skipping poll', [
                'id' => $this->imageGenerationRequest->id,
                'status' => $this->imageGenerationRequest->status,
            ]);

            return;
        }

        // Skip if no external_id (task was never created)
        if (empty($this->imageGenerationRequest->external_id)) {
            Log::warning('Image generation request has no external_id, marking as failed', [
                'id' => $this->imageGenerationRequest->id,
            ]);

            $this->imageGenerationRequest->update([
                'status' => 'failed',
                'error_message' => 'No task ID from provider',
            ]);

            return;
        }

        try {
            // Check task status from Banana API
            $result = $generator->getTaskStatus($this->imageGenerationRequest->external_id);

            $status = strtolower($result['status']);

            // Handle different statuses
            if (in_array($status, ['completed', 'success', 'done'])) {
                $this->processCompletedImage($result['imageUrl']);
            } elseif (in_array($status, ['failed', 'error'])) {
                $this->imageGenerationRequest->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Image generation failed',
                ]);

                Log::error('Image generation failed', [
                    'id' => $this->imageGenerationRequest->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            } else {
                // Status is still pending/processing, re-dispatch this job to check again
                Log::info('Image still processing, will check again in 30 seconds', [
                    'id' => $this->imageGenerationRequest->id,
                    'status' => $status,
                    'attempt' => $this->attempts(),
                ]);

                $this->imageGenerationRequest->update(['status' => 'processing']);

                // Re-dispatch the job with a delay
                $this->release(30);
            }
        } catch (\Throwable $e) {
            Log::error('Error polling image generation status', [
                'id' => $this->imageGenerationRequest->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // If we've exhausted all attempts, mark as failed
            if ($this->attempts() >= $this->tries) {
                $this->imageGenerationRequest->update([
                    'status' => 'failed',
                    'error_message' => 'Max polling attempts reached: '.$e->getMessage(),
                ]);
            } else {
                // Otherwise, re-throw to let Laravel's queue handle the retry
                throw $e;
            }
        }
    }

    /**
     * Process completed image by downloading and storing it.
     */
    private function processCompletedImage(?string $imageUrl): void
    {
        if (empty($imageUrl)) {
            $this->imageGenerationRequest->update([
                'status' => 'failed',
                'error_message' => 'No image URL provided by provider',
            ]);

            return;
        }

        try {
            // Download the image
            $imageResponse = Http::timeout(120)->get($imageUrl);

            if (! $imageResponse->successful()) {
                throw new \RuntimeException('Failed to download image from provider');
            }

            // Determine target model and storage directory
            $directory = 'misc';
            $attribute = $this->imageGenerationRequest->metadata['attribute'] ?? null;
            $baseName = uniqid('', true);

            // Load target model - try multiple approaches for robustness
            $target = null;
            $request = $this->imageGenerationRequest;

            // First, try the polymorphic relationship directly
            if ($request->targetable_type && $request->targetable_id) {
                $targetClass = $request->targetable_type;
                if (class_exists($targetClass)) {
                    $target = $targetClass::find($request->targetable_id);
                }
            }

            // Fallback to morphTo relationship if direct load failed
            if (! $target) {
                $target = $request->targetable;
            }

            // Backward compatibility with legacy post_id column
            if (! $target && $request->post_id) {
                $target = \App\Models\Post::query()->find($request->post_id);
            }

            if ($target instanceof \App\Models\Post) {
                $directory = 'posts';
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
                    // For simple attributes, set directly and save
                    $target->{$attribute} = $publicUrl;
                    $saved = $target->save();

                    Log::info('Simple attribute update via job', [
                        'model' => get_class($target),
                        'model_id' => $target->id,
                        'attribute' => $attribute,
                        'value' => $publicUrl,
                        'saved' => $saved,
                    ]);

                    // Verify the update persisted
                    $target->refresh();
                    if ($target->{$attribute} !== $publicUrl) {
                        Log::error('Attribute update failed to persist via job', [
                            'model' => get_class($target),
                            'model_id' => $target->id,
                            'attribute' => $attribute,
                            'expected' => $publicUrl,
                            'actual' => $target->{$attribute},
                        ]);
                    }
                }
            } else {
                Log::warning('Cannot update model attribute via job - missing target or attribute', [
                    'has_target' => $target !== null,
                    'attribute' => $attribute,
                    'request_id' => $this->imageGenerationRequest->id,
                ]);
            }

            $this->imageGenerationRequest->update([
                'status' => 'completed',
                'image_path' => $filename,
                'image_url' => $publicUrl,
            ]);

            Log::info('Image generation completed via polling', [
                'id' => $this->imageGenerationRequest->id,
                'url' => $publicUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error processing completed image', [
                'id' => $this->imageGenerationRequest->id,
                'error' => $e->getMessage(),
            ]);

            $this->imageGenerationRequest->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update a nested attribute in the model (e.g., "content.0.data.url").
     */
    private function updateNestedAttribute($model, string $path, $value): void
    {
        $parts = explode('.', $path);
        $firstKey = array_shift($parts);

        // Get the current value of the first key (fresh copy)
        $model->refresh();
        $data = $model->{$firstKey};

        if (! is_array($data)) {
            Log::warning('Nested attribute update failed via job: not an array', [
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
                    Log::warning('Nested attribute path not found via job', [
                        'model' => get_class($model),
                        'path' => $path,
                        'part' => $part,
                    ]);

                    return;
                }
                $current = &$current[$part];
            }
        }

        // Force Laravel to recognize the change by setting the attribute directly
        $model->{$firstKey} = $data;
        $saved = $model->save();

        Log::info('Nested attribute updated via job', [
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
            Log::error('Nested attribute update failed to persist via job', [
                'model' => get_class($model),
                'model_id' => $model->id,
                'path' => $path,
                'expected' => $value,
                'actual' => $actualValue,
            ]);
        }
    }
}
