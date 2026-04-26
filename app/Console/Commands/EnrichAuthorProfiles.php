<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Services\AI\AuthorProfileEnricherService;
use Illuminate\Console\Command;

class EnrichAuthorProfiles extends Command
{
    protected $signature = 'authors:enrich
        {--author= : Enrich a specific author by ID}
        {--create-new=0 : Number of new authors to create}';

    protected $description = 'Enrich author profiles with AI-generated personas and optionally create new authors';

    public function __construct(
        private readonly AuthorProfileEnricherService $enricher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $createNew = (int) $this->option('create-new');
        $specificAuthorId = $this->option('author');

        // Collect existing profiles for differentiation
        $existingProfiles = $this->getExistingProfiles();

        // Enrich existing authors
        if ($specificAuthorId) {
            $author = Author::find($specificAuthorId);
            if (! $author) {
                $this->error("Author with ID {$specificAuthorId} not found.");

                return self::FAILURE;
            }

            $this->enrichSingleAuthor($author, $existingProfiles);
        } else {
            $authors = Author::where('is_active', true)->get();
            $this->info("Enriching {$authors->count()} existing authors...");

            foreach ($authors as $author) {
                $this->enrichSingleAuthor($author, $existingProfiles);
                // Update profiles list after each enrichment for differentiation
                $existingProfiles = $this->getExistingProfiles();
            }
        }

        // Create new authors
        if ($createNew > 0) {
            $this->info("Creating {$createNew} new authors...");

            for ($i = 0; $i < $createNew; $i++) {
                $existingProfiles = $this->getExistingProfiles();

                try {
                    $newAuthor = $this->enricher->createNewAuthor($existingProfiles);
                    $this->info("  Created: {$newAuthor->name}");
                    $this->line("    Tone: {$newAuthor->tone}");
                    $this->line('    Expertise: '.implode(', ', $newAuthor->expertise_areas ?? []));
                } catch (\Throwable $e) {
                    $this->error("  Failed to create author ".($i + 1).": {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info('Done! Summary:');
        $this->table(
            ['Name', 'Tone', 'Expertise', 'Voice Bible'],
            Author::where('is_active', true)->get()->map(fn (Author $a) => [
                $a->name,
                $a->tone ?? '-',
                implode(', ', array_slice($a->expertise_areas ?? [], 0, 3)),
                $a->voice_bible ? 'Yes ('.str_word_count($a->voice_bible).' words)' : 'No',
            ])->toArray()
        );

        return self::SUCCESS;
    }

    private function enrichSingleAuthor(Author $author, array $existingProfiles): void
    {
        $this->info("  Enriching: {$author->name}...");

        try {
            $this->enricher->enrichAuthor($author, $existingProfiles);
            $author->refresh();
            $this->info("    Tone: {$author->tone}");
            $this->info('    Expertise: '.implode(', ', $author->expertise_areas ?? []));
            $this->info('    Voice Bible: '.($author->voice_bible ? 'Generated' : 'Missing'));
        } catch (\Throwable $e) {
            $this->error("    Failed: {$e->getMessage()}");
        }
    }

    private function getExistingProfiles(): array
    {
        return Author::where('is_active', true)
            ->get()
            ->map(fn (Author $a) => [
                'name' => $a->name,
                'tone' => $a->tone,
                'expertise_areas' => $a->expertise_areas,
                'sentence_style' => $a->sentence_style,
                'personality_traits' => $a->personality_traits,
            ])
            ->toArray();
    }
}
