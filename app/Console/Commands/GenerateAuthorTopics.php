<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Services\AI\TopicGeneratorService;
use Illuminate\Console\Command;

class GenerateAuthorTopics extends Command
{
    protected $signature = 'topics:generate
        {--author= : Generate topics for a specific author ID}
        {--count=10 : Number of topics to generate per author}';

    protected $description = 'Generate AI-powered topic ideas for authors based on their expertise and existing content';

    public function __construct(
        private readonly TopicGeneratorService $topicGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = (int) $this->option('count');
        $authorId = $this->option('author');

        if ($authorId) {
            $authors = Author::where('id', $authorId)->where('is_active', true)->get();

            if ($authors->isEmpty()) {
                $this->error("Active author with ID {$authorId} not found.");

                return self::FAILURE;
            }
        } else {
            $authors = Author::where('is_active', true)->get();
        }

        $this->info("Generating {$count} topics for {$authors->count()} author(s)...");

        foreach ($authors as $author) {
            $this->info("  {$author->name}:");
            $unused = $author->unusedTopicCount();
            $this->line("    Current unused topics: {$unused}");

            try {
                $topics = $this->topicGenerator->generateTopics($author, $count);

                foreach ($topics as $topic) {
                    $this->line("    + [{$topic->category}] {$topic->topic}");
                }

                $this->info("    Generated {$topics->count()} topics.");
            } catch (\Throwable $e) {
                $this->error("    Failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
