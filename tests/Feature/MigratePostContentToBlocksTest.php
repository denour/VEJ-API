<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use App\Models\PostBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigratePostContentToBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_command_converts_json_content_to_blocks(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'content' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => 'Test paragraph content'],
                ],
                [
                    'type' => 'heading',
                    'data' => ['text' => 'Test Heading', 'level' => 2],
                ],
                [
                    'type' => 'image',
                    'data' => [
                        'url' => 'https://example.com/image.jpg',
                        'alt' => 'Test alt',
                    ],
                ],
            ],
        ]);

        $this->artisan('posts:migrate-content-to-blocks')
            ->assertSuccessful();

        $this->assertCount(3, $post->fresh()->blocks);

        $blocks = $post->fresh()->blocks;
        $this->assertEquals('paragraph', $blocks[0]->type);
        $this->assertEquals('Test paragraph content', $blocks[0]->content);
        $this->assertEquals('heading', $blocks[1]->type);
        $this->assertEquals('Test Heading', $blocks[1]->title);
        $this->assertEquals('image', $blocks[2]->type);
        $this->assertEquals('https://example.com/image.jpg', $blocks[2]->data['url']);
    }

    public function test_migration_command_with_dry_run_does_not_persist(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'content' => [
                ['type' => 'paragraph', 'data' => ['text' => 'Test']],
            ],
        ]);

        $this->artisan('posts:migrate-content-to-blocks', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertCount(0, $post->fresh()->blocks);
    }

    public function test_migration_command_updates_table_of_contents(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'content' => [
                ['type' => 'heading', 'data' => ['text' => 'First Section', 'level' => 2]],
                ['type' => 'paragraph', 'data' => ['text' => 'Content']],
                ['type' => 'heading', 'data' => ['text' => 'Second Section', 'level' => 2]],
            ],
            'list' => [], // Empty TOC
        ]);

        $this->artisan('posts:migrate-content-to-blocks')
            ->assertSuccessful();

        $post->refresh();
        $toc = $post->list;

        $this->assertCount(2, $toc);
        $this->assertEquals('First Section', $toc[0]['text']);
        $this->assertEquals('Second Section', $toc[1]['text']);
    }

    public function test_migration_command_handles_all_block_types(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'content' => [
                ['type' => 'paragraph', 'data' => ['text' => 'Paragraph']],
                ['type' => 'heading', 'data' => ['text' => 'Heading', 'level' => 2]],
                ['type' => 'image', 'data' => ['url' => 'image.jpg', 'alt' => 'Alt']],
                ['type' => 'list', 'data' => ['items' => ['Item 1', 'Item 2']]],
                ['type' => 'quote', 'data' => ['text' => 'Quote text', 'author' => 'Author']],
                ['type' => 'code', 'data' => ['code' => 'console.log()', 'language' => 'javascript']],
                ['type' => 'video', 'data' => ['url' => 'https://youtube.com/watch?v=123']],
            ],
        ]);

        $this->artisan('posts:migrate-content-to-blocks')
            ->assertSuccessful();

        $blocks = $post->fresh()->blocks;
        $this->assertCount(7, $blocks);

        $types = $blocks->pluck('type')->toArray();
        $this->assertEquals(['paragraph', 'heading', 'image', 'list', 'quote', 'code', 'video'], $types);
    }

    public function test_migration_command_preserves_order(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'content' => [
                ['type' => 'heading', 'data' => ['text' => 'First']],
                ['type' => 'paragraph', 'data' => ['text' => 'Second']],
                ['type' => 'image', 'data' => ['url' => 'third.jpg']],
            ],
        ]);

        $this->artisan('posts:migrate-content-to-blocks')
            ->assertSuccessful();

        $blocks = $post->fresh()->blocks;
        $this->assertEquals(0, $blocks[0]->order);
        $this->assertEquals(1, $blocks[1]->order);
        $this->assertEquals(2, $blocks[2]->order);
    }

    public function test_migration_command_skips_posts_without_content(): void
    {
        $author = Author::factory()->create();
        $post1 = Post::factory()->for($author)->create(['content' => null]);
        $post2 = Post::factory()->for($author)->create(['content' => []]);

        $this->artisan('posts:migrate-content-to-blocks')
            ->assertSuccessful();

        $this->assertCount(0, $post1->fresh()->blocks);
        $this->assertCount(0, $post2->fresh()->blocks);
    }

    public function test_migration_command_deletes_existing_blocks_before_creating_new_ones(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'content' => [
                ['type' => 'paragraph', 'data' => ['text' => 'New content']],
            ],
        ]);

        // Create some existing blocks
        PostBlock::factory()->for($post)->count(3)->create();
        $this->assertCount(3, $post->fresh()->blocks);

        $this->artisan('posts:migrate-content-to-blocks')
            ->assertSuccessful();

        // Should have only 1 block from the content
        $this->assertCount(1, $post->fresh()->blocks);
        $this->assertEquals('New content', $post->fresh()->blocks[0]->content);
    }
}
