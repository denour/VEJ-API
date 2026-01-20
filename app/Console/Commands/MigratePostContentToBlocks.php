<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\PostBlock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePostContentToBlocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:migrate-content-to-blocks {--dry-run : Preview changes without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate post content from JSON to PostBlock records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        $posts = Post::whereNotNull('content')->get();

        if ($posts->isEmpty()) {
            $this->info('No posts with content found.');

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} posts to migrate");
        $this->newLine();

        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();

        $totalBlocks = 0;
        $errors = [];

        foreach ($posts as $post) {
            try {
                $blocksCreated = $this->migratePost($post, $dryRun);
                $totalBlocks += $blocksCreated;
            } catch (\Exception $e) {
                $errors[] = [
                    'post_id' => $post->id,
                    'title' => $post->title,
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Migration completed!");
        $this->info("Posts processed: {$posts->count()}");
        $this->info("Blocks created: {$totalBlocks}");

        if (! empty($errors)) {
            $this->newLine();
            $this->error("❌ Errors encountered: " . count($errors));
            $this->table(
                ['Post ID', 'Title', 'Error'],
                collect($errors)->map(fn ($e) => [$e['post_id'], $e['title'], $e['error']])->toArray()
            );
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to save changes.');
        }

        return self::SUCCESS;
    }

    private function migratePost(Post $post, bool $dryRun): int
    {
        $content = $post->content;

        if (empty($content) || ! is_array($content)) {
            return 0;
        }

        $blocks = [];
        $order = 0;

        foreach ($content as $block) {
            $blockData = $this->transformBlock($block, $order);

            if ($blockData) {
                $blocks[] = $blockData;
                $order++;
            }
        }

        if (! $dryRun && ! empty($blocks)) {
            DB::transaction(function () use ($post, $blocks) {
                // Delete existing blocks for this post
                $post->blocks()->delete();

                // Create new blocks
                foreach ($blocks as $blockData) {
                    $post->blocks()->create($blockData);
                }

                // Update the table of contents from heading blocks
                $toc = $post->generateTableOfContents();
                $post->update(['list' => $toc]);
            });
        }

        return count($blocks);
    }

    private function transformBlock(array $block, int $order): ?array
    {
        $type = $block['type'] ?? null;

        if (! $type) {
            return null;
        }

        $data = $block['data'] ?? [];

        $transformed = [
            'type' => $type,
            'order' => $order,
            'title' => null,
            'content' => null,
            'data' => null,
        ];

        switch ($type) {
            case 'paragraph':
                $transformed['content'] = $data['text'] ?? null;
                break;

            case 'heading':
                $transformed['title'] = $data['text'] ?? null;
                $transformed['data'] = [
                    'level' => $data['level'] ?? 2,
                ];
                break;

            case 'image':
                $transformed['data'] = [
                    'url' => $data['url'] ?? null,
                    'alt' => $data['alt'] ?? null,
                    'caption' => $data['caption'] ?? null,
                    'prompt' => $data['prompt'] ?? null,
                ];
                break;

            case 'list':
                $transformed['title'] = $data['title'] ?? null;
                $transformed['data'] = [
                    'items' => $data['items'] ?? [],
                    'ordered' => $data['ordered'] ?? false,
                ];
                break;

            case 'quote':
                $transformed['content'] = $data['text'] ?? null;
                $transformed['data'] = [
                    'author' => $data['author'] ?? null,
                    'source' => $data['source'] ?? null,
                ];
                break;

            case 'code':
                $transformed['title'] = $data['title'] ?? null;
                $transformed['content'] = $data['code'] ?? null;
                $transformed['data'] = [
                    'language' => $data['language'] ?? 'text',
                    'filename' => $data['filename'] ?? null,
                ];
                break;

            case 'video':
                $transformed['title'] = $data['title'] ?? null;
                $transformed['data'] = [
                    'url' => $data['url'] ?? null,
                    'provider' => $data['provider'] ?? 'youtube',
                    'thumbnail' => $data['thumbnail'] ?? null,
                    'caption' => $data['caption'] ?? null,
                ];
                break;

            default:
                // Unknown type, skip it
                return null;
        }

        return $transformed;
    }
}
