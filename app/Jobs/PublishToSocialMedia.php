<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Social\SocialMediaPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PublishToSocialMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Post $post,
    ) {}

    public function handle(SocialMediaPublisher $publisher): void
    {
        // Skip if already published to social media
        if ($this->post->social_published_at) {
            Log::info('Post already published to social media, skipping', [
                'post_id' => $this->post->id,
            ]);

            return;
        }

        $results = $publisher->publishPost($this->post);

        Log::info('Social media publishing completed', [
            'post_id' => $this->post->id,
            'facebook' => $results['facebook'] ? 'success' : 'skipped/failed',
            'instagram' => $results['instagram'] ? 'success' : 'skipped/failed',
        ]);
    }
}
