<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_block_can_be_created(): void
    {
        $post = Post::factory()->create();

        $block = PostBlock::factory()->for($post)->paragraph()->create();

        $this->assertDatabaseHas('post_blocks', [
            'id' => $block->id,
            'post_id' => $post->id,
            'type' => 'paragraph',
        ]);
    }

    public function test_post_has_blocks_relationship(): void
    {
        $post = Post::factory()->create();

        PostBlock::factory()->for($post)->heading()->ordered(0)->create();
        PostBlock::factory()->for($post)->paragraph()->ordered(1)->create();
        PostBlock::factory()->for($post)->image()->ordered(2)->create();

        $this->assertCount(3, $post->blocks);
        $this->assertEquals('heading', $post->blocks[0]->type);
        $this->assertEquals('paragraph', $post->blocks[1]->type);
        $this->assertEquals('image', $post->blocks[2]->type);
    }

    public function test_blocks_are_ordered_correctly(): void
    {
        $post = Post::factory()->create();

        PostBlock::factory()->for($post)->paragraph()->ordered(2)->create(['title' => 'Third']);
        PostBlock::factory()->for($post)->heading()->ordered(0)->create(['title' => 'First']);
        PostBlock::factory()->for($post)->paragraph()->ordered(1)->create(['title' => 'Second']);

        $blocks = $post->blocks;

        $this->assertEquals('First', $blocks[0]->title);
        $this->assertEquals('Second', $blocks[1]->title);
        $this->assertEquals('Third', $blocks[2]->title);
    }

    public function test_image_block_returns_placeholder_when_no_url(): void
    {
        $block = PostBlock::factory()->imagePending()->create();

        $url = $block->getImageUrl();

        $this->assertStringContainsString('placehold', $url);
        $this->assertStringContainsString('Generando+Imagen', $url);
    }

    public function test_image_block_returns_actual_url_when_present(): void
    {
        $block = PostBlock::factory()->image()->create();

        $url = $block->getImageUrl();

        $this->assertEquals($block->data['url'], $url);
        $this->assertStringNotContainsString('placehold', $url);
    }

    public function test_has_pending_image_returns_true_for_image_without_url(): void
    {
        $block = PostBlock::factory()->imagePending()->create();

        $this->assertTrue($block->hasPendingImage());
    }

    public function test_has_pending_image_returns_false_for_image_with_url(): void
    {
        $block = PostBlock::factory()->image()->create();

        $this->assertFalse($block->hasPendingImage());
    }

    public function test_has_pending_image_returns_false_for_non_image_blocks(): void
    {
        $block = PostBlock::factory()->paragraph()->create();

        $this->assertFalse($block->hasPendingImage());
    }

    public function test_post_generates_table_of_contents_from_heading_blocks(): void
    {
        $post = Post::factory()->create();

        PostBlock::factory()->for($post)->heading()->ordered(0)->create(['title' => 'Introduction']);
        PostBlock::factory()->for($post)->paragraph()->ordered(1)->create();
        PostBlock::factory()->for($post)->heading()->ordered(2)->create(['title' => 'Conclusion']);

        $toc = $post->generateTableOfContents();

        $this->assertCount(2, $toc);
        $this->assertEquals('Introduction', $toc[0]['text']);
        $this->assertEquals('Conclusion', $toc[1]['text']);
    }

    public function test_post_generates_legacy_content_from_blocks(): void
    {
        $post = Post::factory()->create();

        PostBlock::factory()->for($post)->paragraph()->ordered(0)->create([
            'content' => 'Test paragraph content',
        ]);
        PostBlock::factory()->for($post)->heading()->ordered(1)->create([
            'title' => 'Test Heading',
            'data' => ['level' => 2],
        ]);
        PostBlock::factory()->for($post)->image()->ordered(2)->create([
            'data' => [
                'url' => 'https://example.com/image.jpg',
                'alt' => 'Test alt',
            ],
        ]);

        $content = $post->getContentFromBlocks();

        $this->assertCount(3, $content);
        $this->assertEquals('paragraph', $content[0]['type']);
        $this->assertEquals('Test paragraph content', $content[0]['data']['text']);
        $this->assertEquals('heading', $content[1]['type']);
        $this->assertEquals('Test Heading', $content[1]['data']['text']);
        $this->assertEquals('image', $content[2]['type']);
        $this->assertEquals('https://example.com/image.jpg', $content[2]['data']['url']);
    }

    public function test_deleting_post_cascades_to_blocks(): void
    {
        $post = Post::factory()->create();

        $block1 = PostBlock::factory()->for($post)->create();
        $block2 = PostBlock::factory()->for($post)->create();

        $post->delete();

        $this->assertDatabaseMissing('post_blocks', ['id' => $block1->id]);
        $this->assertDatabaseMissing('post_blocks', ['id' => $block2->id]);
    }

    public function test_different_block_types_can_be_created(): void
    {
        $post = Post::factory()->create();

        $paragraph = PostBlock::factory()->for($post)->paragraph()->create();
        $heading = PostBlock::factory()->for($post)->heading()->create();
        $image = PostBlock::factory()->for($post)->image()->create();
        $list = PostBlock::factory()->for($post)->list()->create();
        $quote = PostBlock::factory()->for($post)->quote()->create();
        $code = PostBlock::factory()->for($post)->code()->create();
        $video = PostBlock::factory()->for($post)->video()->create();

        $this->assertEquals('paragraph', $paragraph->type);
        $this->assertEquals('heading', $heading->type);
        $this->assertEquals('image', $image->type);
        $this->assertEquals('list', $list->type);
        $this->assertEquals('quote', $quote->type);
        $this->assertEquals('code', $code->type);
        $this->assertEquals('video', $video->type);
    }
}
