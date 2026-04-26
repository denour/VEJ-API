<?php

namespace App\Console\Commands;

use App\Jobs\PublishToSocialMedia;
use App\Models\Author;
use App\Services\AI\PostGeneratorService;
use App\Services\AI\TopicGeneratorService;
use Illuminate\Console\Command;

class GenerateDailyPost extends Command
{
    private const MIN_TOPICS_THRESHOLD = 3;

    private const REFILL_COUNT = 10;

    protected $signature = 'posts:generate-daily';

    protected $description = 'Generate and publish a daily blog post using AI with round-robin author rotation';

    public function __construct(
        private readonly PostGeneratorService $postGenerator,
        private readonly TopicGeneratorService $topicGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Generating daily post...');

        try {
            // 1. Round-robin: select active author with oldest last_used_at
            $author = $this->selectNextAuthor();

            if (! $author) {
                $this->error('No active authors found.');

                return self::FAILURE;
            }

            $this->line("Author: {$author->name}");

            // 2. Auto-refill topic pool if running low
            if ($author->unusedTopicCount() < self::MIN_TOPICS_THRESHOLD) {
                $this->line('Topic pool low, generating more...');
                $this->topicGenerator->generateTopics($author, self::REFILL_COUNT);
            }

            // 3. Pick next topic from pool
            $authorTopic = $author->nextAvailableTopic();
            $topicLabel = $authorTopic?->topic ?? 'AI free choice';
            $this->line("Topic: {$topicLabel}");

            // 4. Generate post with auto-publish
            $post = $this->postGenerator->generatePost($author, $authorTopic?->topic, [
                'status' => 'published',
                'published_at' => now(),
            ]);

            // 5. Mark topic as used
            $authorTopic?->markUsed($post);

            // 6. Update author stats
            $author->incrementPostCount();

            // 7. Dispatch social media publishing (5 min delay for images to complete)
            PublishToSocialMedia::dispatch($post)->delay(now()->addMinutes(5));

            $this->newLine();
            $this->info("Published: {$post->title}");
            $this->line("Category: {$post->category}");
            $this->line("Slug: {$post->slug}");
            $this->line("Reading time: {$post->reading_time} min");
            $this->line('Social media: scheduled in 5 minutes');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * Select the active author who has gone longest without publishing.
     * Authors with null last_used_at (never used) come first.
     */
    private function selectNextAuthor(): ?Author
    {
        return Author::query()
            ->where('is_active', true)
            ->get()
            ->sortBy(function (Author $author): string {
                return $author->generation_stats['last_used_at'] ?? '1970-01-01T00:00:00+00:00';
            })
            ->first();
    }
}
