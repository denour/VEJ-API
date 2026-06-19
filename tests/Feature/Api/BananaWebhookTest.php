<?php

namespace Tests\Feature\Api;

use App\Models\Author;
use App\Models\ImageGenerationRequest;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BananaWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_updates_post_cover_image(): void
    {
        Storage::fake('s3');

        // Fake image download
        Http::fake([
            'example.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        ]);

        $post = Post::factory()->create();

        $req = ImageGenerationRequest::query()->create([
            'external_id' => 'task-123',
            'post_id' => $post->getKey(),
            'token' => 'tok_abc',
            'prompt' => 'Cover image',
            'size' => '1024x1024',
            'status' => 'pending',
        ]);

        $payload = [
            'taskId' => 'task-123',
            'data' => [
                'response' => [
                    'resultImageUrl' => 'http://example.com/image.png',
                ],
            ],
        ];

        $this->postJson('/api/webhooks/banana', $payload)
            ->assertOk()
            ->assertJsonStructure(['message', 'url']);

        $post->refresh();
        $this->assertNotNull($post->cover_image);

        // Ensure file was stored on s3 disk
        $files = Storage::disk('s3')->allFiles('posts');
        $this->assertNotEmpty($files);

        // Request record updated
        $req->refresh();
        $this->assertEquals('completed', $req->status);
        $this->assertNotNull($req->image_url);
    }

    public function test_webhook_ignores_unknown_task(): void
    {
        $this->postJson('/api/webhooks/banana', ['taskId' => 'unknown-task'])
            ->assertStatus(202);
    }

    public function test_webhook_updates_author_image(): void
    {
        Storage::fake('s3');

        Http::fake([
            'example.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        ]);

        $author = Author::factory()->create();

        $req = ImageGenerationRequest::query()->create([
            'external_id' => 'task-auth-1',
            'targetable_type' => Author::class,
            'targetable_id' => $author->getKey(),
            'token' => 'tok_auth',
            'prompt' => 'Author avatar',
            'size' => '1024x1024',
            'status' => 'pending',
        ]);

        $payload = [
            'taskId' => 'task-auth-1',
            'data' => [
                'response' => [
                    'resultImageUrl' => 'http://example.com/author.png',
                ],
            ],
        ];

        $this->postJson('/api/webhooks/banana', $payload)
            ->assertOk()
            ->assertJsonStructure(['message', 'url']);

        $author->refresh();
        $this->assertNotNull($author->avatar_url);

        $files = Storage::disk('s3')->allFiles('authors');
        $this->assertNotEmpty($files);

        $req->refresh();
        $this->assertEquals('completed', $req->status);
        $this->assertNotNull($req->image_url);
    }

    public function test_webhook_updates_product_image(): void
    {
        Storage::fake('s3');

        Http::fake([
            'example.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        ]);

        $product = Product::factory()->create();

        $req = ImageGenerationRequest::query()->create([
            'external_id' => 'task-prod-1',
            'targetable_type' => Product::class,
            'targetable_id' => $product->getKey(),
            'token' => 'tok_prod',
            'prompt' => 'Product image',
            'size' => '1024x1024',
            'status' => 'pending',
        ]);

        $payload = [
            'taskId' => 'task-prod-1',
            'data' => [
                'response' => [
                    'resultImageUrl' => 'http://example.com/product.png',
                ],
            ],
        ];

        $this->postJson('/api/webhooks/banana', $payload)
            ->assertOk()
            ->assertJsonStructure(['message', 'url']);

        $product->refresh();
        $this->assertNotNull($product->image);

        $files = Storage::disk('s3')->allFiles('products');
        $this->assertNotEmpty($files);

        $req->refresh();
        $this->assertEquals('completed', $req->status);
        $this->assertNotNull($req->image_url);
    }

    public function test_webhook_updates_post_cover_image_via_targetable(): void
    {
        Storage::fake('s3');

        Http::fake([
            'example.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        ]);

        $post = Post::factory()->create(['cover_image' => null]);

        // Create request with targetable_type/targetable_id (new format)
        $req = ImageGenerationRequest::query()->create([
            'external_id' => 'task-targetable-123',
            'targetable_type' => Post::class,
            'targetable_id' => $post->getKey(),
            'token' => 'tok_targetable',
            'prompt' => 'Cover image via targetable',
            'size' => '1024x1024',
            'status' => 'pending',
            'metadata' => [
                'attribute' => 'cover_image',
                'model_name' => 'Post',
            ],
        ]);

        $payload = [
            'taskId' => 'task-targetable-123',
            'imageUrl' => 'http://example.com/cover.png',
        ];

        $this->postJson('/api/webhooks/banana', $payload)
            ->assertOk()
            ->assertJsonStructure(['message', 'url']);

        $post->refresh();
        $this->assertNotNull($post->cover_image, 'Post cover_image should be updated');
        $this->assertStringContainsString('posts/', $post->cover_image);

        $req->refresh();
        $this->assertEquals('completed', $req->status);
    }

    public function test_webhook_updates_nested_content_image(): void
    {
        Storage::fake('s3');

        Http::fake([
            'example.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        ]);

        // Create post with content blocks including an image block
        $post = Post::factory()->create([
            'content' => [
                [
                    'type' => 'heading',
                    'data' => ['text' => 'Test Heading', 'level' => 2],
                ],
                [
                    'type' => 'image',
                    'data' => ['url' => null, 'alt' => 'Test image', 'caption' => 'Test caption'],
                ],
                [
                    'type' => 'paragraph',
                    'data' => ['text' => 'Some paragraph text'],
                ],
            ],
        ]);

        // Create request for the content image at index 1
        $req = ImageGenerationRequest::query()->create([
            'external_id' => 'task-content-img',
            'targetable_type' => Post::class,
            'targetable_id' => $post->getKey(),
            'token' => 'tok_content',
            'prompt' => 'Content block image',
            'size' => '1024x1024',
            'status' => 'pending',
            'metadata' => [
                'attribute' => 'content.1.data.url',
                'model_name' => 'Post',
                'block_index' => 1,
            ],
        ]);

        $payload = [
            'taskId' => 'task-content-img',
            'imageUrl' => 'http://example.com/content-image.png',
        ];

        $this->postJson('/api/webhooks/banana', $payload)
            ->assertOk()
            ->assertJsonStructure(['message', 'url']);

        $post->refresh();

        // Verify the nested content block image URL was updated
        $this->assertNotNull($post->content[1]['data']['url'], 'Content block image URL should be updated');
        $this->assertStringContainsString('posts/', $post->content[1]['data']['url']);

        // Other blocks should remain unchanged
        $this->assertEquals('heading', $post->content[0]['type']);
        $this->assertEquals('paragraph', $post->content[2]['type']);

        $req->refresh();
        $this->assertEquals('completed', $req->status);
    }

    public function test_webhook_handles_missing_target_gracefully(): void
    {
        Storage::fake('s3');

        Http::fake([
            'example.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        ]);

        // Create request without a valid target (post doesn't exist)
        $req = ImageGenerationRequest::query()->create([
            'external_id' => 'task-no-target',
            'targetable_type' => Post::class,
            'targetable_id' => '01NONEXISTENT000000000000',
            'token' => 'tok_notarget',
            'prompt' => 'Image for missing post',
            'size' => '1024x1024',
            'status' => 'pending',
            'metadata' => [
                'attribute' => 'cover_image',
            ],
        ]);

        $payload = [
            'taskId' => 'task-no-target',
            'imageUrl' => 'http://example.com/orphan.png',
        ];

        // Should still complete successfully (image stored, but no model updated)
        $this->postJson('/api/webhooks/banana', $payload)
            ->assertOk();

        $req->refresh();
        $this->assertEquals('completed', $req->status);
        $this->assertNotNull($req->image_url);
    }
}
