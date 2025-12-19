<?php

namespace App\Jobs;

use App\Models\ImageGenerationRequest;
use App\Services\AI\BananaImageGenerator;
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
    public function handle(BananaImageGenerator $generator): void
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

            $target = $this->imageGenerationRequest->targetable;

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
                $target->update([$attribute => $publicUrl]);
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
}
