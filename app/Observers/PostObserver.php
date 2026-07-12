<?php

namespace App\Observers;

use App\Jobs\GenerateModelImage;
use App\Models\Post;

class PostObserver
{
    /**
     * When true, the observer's image-generation dispatch is suppressed.
     *
     * PostGeneratorService owns image generation for posts it creates (writing
     * to PostBlock rows synchronously). It mutes this observer during that flow
     * so the legacy `content`-JSON path here can't generate a second, duplicate
     * paid image and overwrite what the service produced.
     */
    public static bool $muted = false;

    /**
     * Run a callback with observer image-generation suppressed, restoring the
     * previous state afterwards (safe to nest).
     */
    public static function muted(callable $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    /**
     * Handle the Post "created" event.
     */
    public function created(Post $post): void
    {
        if (self::$muted) {
            return;
        }

        // Only generate cover image if one wasn't manually uploaded
        if (empty($post->cover_image)) {
            GenerateModelImage::dispatch($post, 'cover_image');
        }

        // Generate images for content blocks that have type 'image' but no URL
        if (! empty($post->content) && is_array($post->content)) {
            $this->generateContentImages($post);
        }
    }

    /**
     * Handle the Post "updated" event.
     */
    public function updated(Post $post): void
    {
        if (self::$muted) {
            return;
        }

        // Check if content was updated and generate missing images
        if ($post->wasChanged('content') && ! empty($post->content) && is_array($post->content)) {
            $this->generateContentImages($post);
        }
    }

    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void
    {
        //
    }

    /**
     * Handle the Post "restored" event.
     */
    public function restored(Post $post): void
    {
        //
    }

    /**
     * Handle the Post "force deleted" event.
     */
    public function forceDeleted(Post $post): void
    {
        //
    }

    /**
     * Generate images for content blocks.
     */
    private function generateContentImages(Post $post): void
    {
        foreach ($post->content as $index => $block) {
            if (
                ($block['type'] ?? null) === 'image'
                && empty($block['data']['url'] ?? null)
                && ! empty($block['data']['alt'] ?? null)
            ) {
                // Generate image using the alt text as prompt
                $prompt = $block['data']['alt'];
                GenerateModelImage::dispatch($post, "content.{$index}.data.url", $prompt);
            }
        }
    }
}
