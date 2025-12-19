<?php

namespace App\Console\Commands;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Models\ImageGenerationRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PollPendingImageGenerations extends Command
{
    protected $signature = 'images:poll-pending';

    protected $description = 'Poll Banana API for pending image generation requests';

    public function handle(ImageGeneratorInterface $generator): int
    {
        // Find all pending/processing requests with an external_id
        $pendingRequests = ImageGenerationRequest::query()
            ->whereIn('status', ['pending', 'processing'])
            ->whereNotNull('external_id')
            ->get();

        if ($pendingRequests->isEmpty()) {
            $this->info('No pending image generation requests found.');

            return self::SUCCESS;
        }

        $this->info("Found {$pendingRequests->count()} pending request(s). Checking status...");

        foreach ($pendingRequests as $request) {
            try {
                $this->checkRequestStatus($request, $generator);
            } catch (\Throwable $e) {
                $this->error("Error processing request #{$request->id}: {$e->getMessage()}");
                Log::error('Error polling image generation request', [
                    'id' => $request->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    private function checkRequestStatus(ImageGenerationRequest $request, ImageGeneratorInterface $generator): void
    {
        $this->line("Checking request #{$request->id} (external_id: {$request->external_id})...");

        // Check task status from Banana API
        $result = $generator->getTaskStatus($request->external_id);
        $status = strtolower($result['status']);

        $this->line("  Status: {$status}");

        // Handle different statuses
        if (in_array($status, ['completed', 'success', 'done'])) {
            $this->info('  ✓ Image completed! Processing...');
            $this->processCompletedImage($request, $result['imageUrl']);
        } elseif (in_array($status, ['failed', 'error'])) {
            $this->error('  ✗ Image generation failed');
            $request->update([
                'status' => 'failed',
                'error_message' => $result['error'] ?? 'Image generation failed',
            ]);

            Log::error('Image generation failed', [
                'id' => $request->id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
        } else {
            // Status is still pending/processing
            $this->line('  ⏳ Still processing...');
            $request->update(['status' => 'processing']);
        }
    }

    private function processCompletedImage(ImageGenerationRequest $request, ?string $imageUrl): void
    {
        if (empty($imageUrl)) {
            $request->update([
                'status' => 'failed',
                'error_message' => 'No image URL provided by provider',
            ]);
            $this->error('  ✗ No image URL provided');

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
            $attribute = $request->metadata['attribute'] ?? null;
            $baseName = uniqid('', true);

            $target = $request->targetable;

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
                    $target->update([$attribute => $publicUrl]);
                }
            }

            $request->update([
                'status' => 'completed',
                'image_path' => $filename,
                'image_url' => $publicUrl,
            ]);

            $this->info("  ✓ Image saved to S3: {$publicUrl}");

            Log::info('Image generation completed via polling', [
                'id' => $request->id,
                'url' => $publicUrl,
            ]);
        } catch (\Throwable $e) {
            $this->error("  ✗ Error processing image: {$e->getMessage()}");

            Log::error('Error processing completed image', [
                'id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            $request->update([
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
