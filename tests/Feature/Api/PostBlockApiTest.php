<?php

namespace Tests\Feature\Api;

use App\Models\Author;
use App\Models\Post;
use App\Models\PostBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostBlockApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_api_includes_blocks(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostBlock::factory()->for($post)->heading()->ordered(0)->create(['title' => 'Introduction']);
        PostBlock::factory()->for($post)->paragraph()->ordered(1)->create(['content' => 'Test content']);

        $response = $this->getJson("/api/v1/posts/{$post->slug}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'blocks' => [
                        '*' => [
                            'id',
                            'type',
                            'title',
                            'content',
                            'data',
                            'order',
                        ],
                    ],
                ],
            ]);

        $blocks = $response->json('data.blocks');
        $this->assertCount(2, $blocks);
        $this->assertEquals('heading', $blocks[0]['type']);
        $this->assertEquals('Introduction', $blocks[0]['title']);
        $this->assertEquals('paragraph', $blocks[1]['type']);
        $this->assertEquals('Test content', $blocks[1]['content']);
    }

    public function test_post_api_includes_legacy_content_format(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostBlock::factory()->for($post)->paragraph()->ordered(0)->create(['content' => 'Test paragraph']);

        $response = $this->getJson("/api/v1/posts/{$post->slug}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'content', // Legacy format
                    'blocks',  // New format
                ],
            ]);

        $content = $response->json('data.content');
        $this->assertIsArray($content);
        $this->assertCount(1, $content);
        $this->assertEquals('paragraph', $content[0]['type']);
        $this->assertEquals('Test paragraph', $content[0]['data']['text']);
    }

    public function test_image_block_returns_placeholder_when_no_url(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostBlock::factory()->for($post)->imagePending()->ordered(0)->create();

        $response = $this->getJson("/api/v1/posts/{$post->slug}");

        $response->assertOk();

        $imageBlock = $response->json('data.blocks.0');
        $this->assertEquals('image', $imageBlock['type']);
        $this->assertStringContainsString('placehold', $imageBlock['data']['url']);
    }

    public function test_post_list_includes_blocks(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostBlock::factory()->for($post)->heading()->create();
        PostBlock::factory()->for($post)->paragraph()->create();

        $response = $this->getJson('/api/v1/posts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'blocks',
                        'content',
                    ],
                ],
            ]);
    }

    public function test_blocks_are_ordered_in_api_response(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostBlock::factory()->for($post)->paragraph()->ordered(2)->create(['content' => 'Third']);
        PostBlock::factory()->for($post)->heading()->ordered(0)->create(['title' => 'First']);
        PostBlock::factory()->for($post)->paragraph()->ordered(1)->create(['content' => 'Second']);

        $response = $this->getJson("/api/v1/posts/{$post->slug}");

        $blocks = $response->json('data.blocks');
        $this->assertEquals(0, $blocks[0]['order']);
        $this->assertEquals(1, $blocks[1]['order']);
        $this->assertEquals(2, $blocks[2]['order']);
    }

    public function test_different_block_types_in_api_response(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostBlock::factory()->for($post)->heading()->ordered(0)->create();
        PostBlock::factory()->for($post)->paragraph()->ordered(1)->create();
        PostBlock::factory()->for($post)->image()->ordered(2)->create();
        PostBlock::factory()->for($post)->list()->ordered(3)->create();
        PostBlock::factory()->for($post)->quote()->ordered(4)->create();
        PostBlock::factory()->for($post)->code()->ordered(5)->create();
        PostBlock::factory()->for($post)->video()->ordered(6)->create();

        $response = $this->getJson("/api/v1/posts/{$post->slug}");

        $blocks = $response->json('data.blocks');
        $this->assertCount(7, $blocks);

        $types = array_column($blocks, 'type');
        $this->assertEquals(['heading', 'paragraph', 'image', 'list', 'quote', 'code', 'video'], $types);
    }
}