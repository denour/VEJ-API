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
        $this->assertNotNull($author->image);

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
}
