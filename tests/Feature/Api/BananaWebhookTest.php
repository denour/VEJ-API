<?php

namespace Tests\Feature\Api;

use App\Models\ImageGenerationRequest;
use App\Models\Post;
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
}
