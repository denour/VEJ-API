<?php

namespace Tests\Feature;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Jobs\GenerateModelImage;
use App\Models\Author;
use App\Models\ImageGenerationRequest;
use App\Models\Post;
use App\Services\AI\MockBananaImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostImageGenerationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.banana.webhook_secret' => 'test-webhook-secret']);

        MockBananaImageGenerator::clearTasks();

        $this->app->singleton(ImageGeneratorInterface::class, function () {
            return new MockBananaImageGenerator;
        });
    }

    /**
     * POST to the banana webhook with the required shared-secret header.
     */
    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Webhook-Secret' => 'test-webhook-secret'])
            ->postJson('/api/webhooks/banana', $payload);
    }

    protected function tearDown(): void
    {
        MockBananaImageGenerator::clearTasks();
        parent::tearDown();
    }

    /**
     * Test the webhook with the REAL NanoBanana payload format.
     * This is the critical test that validates the production fix.
     */
    public function test_webhook_handles_real_nanobanana_payload_format(): void
    {
        Storage::fake('s3');

        Http::fake([
            'tempfile.aiquickdraw.com/*' => Http::response('FAKE_PNG_DATA', 200, ['Content-Type' => 'image/png']),
        ]);

        $author = Author::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'cover_image' => null,
        ]);

        $taskId = '9e4286b7b27960dfeb8e1d279b50b28d';
        $imageUrl = 'https://tempfile.aiquickdraw.com/r/'.$taskId.'_1756467493.jpg';

        // Create the pending request
        ImageGenerationRequest::query()->create([
            'external_id' => $taskId,
            'targetable_type' => Post::class,
            'targetable_id' => $post->id,
            'prompt' => 'Test prompt',
            'status' => 'pending',
            'metadata' => [
                'attribute' => 'cover_image',
                'model_name' => 'Post',
            ],
        ]);

        // Send webhook with the REAL NanoBanana format
        $realNanoBananaPayload = [
            'msg' => 'Image generated successfully.',
            'code' => 200,
            'data' => [
                'taskId' => $taskId,
                'info' => [
                    'resultImageUrl' => $imageUrl,
                ],
            ],
        ];

        $response = $this->postWebhook($realNanoBananaPayload);

        $response->assertOk()
            ->assertJsonStructure(['message', 'url']);

        // Verify the post was updated
        $post->refresh();
        $this->assertNotNull($post->cover_image, 'Post cover_image should be set after webhook');
        $this->assertStringContainsString('posts/', $post->cover_image);

        // Verify request completed
        $this->assertDatabaseHas('image_generation_requests', [
            'external_id' => $taskId,
            'status' => 'completed',
        ]);
    }

    /**
     * Test webhook also works with simplified payload (backward compatibility).
     */
    public function test_webhook_handles_simplified_payload_format(): void
    {
        Storage::fake('s3');

        Http::fake([
            'tempfile.aiquickdraw.com/*' => Http::response('FAKE_PNG_DATA', 200, ['Content-Type' => 'image/png']),
        ]);

        $author = Author::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'cover_image' => null,
        ]);

        $taskId = 'simple-task-123';
        $imageUrl = 'https://tempfile.aiquickdraw.com/simple.png';

        ImageGenerationRequest::query()->create([
            'external_id' => $taskId,
            'targetable_type' => Post::class,
            'targetable_id' => $post->id,
            'prompt' => 'Test prompt',
            'status' => 'pending',
            'metadata' => [
                'attribute' => 'cover_image',
                'model_name' => 'Post',
            ],
        ]);

        // Simplified format (direct taskId and imageUrl)
        $response = $this->postWebhook([
            'taskId' => $taskId,
            'imageUrl' => $imageUrl,
        ]);

        $response->assertOk();

        $post->refresh();
        $this->assertNotNull($post->cover_image);
    }

    public function test_full_flow_job_to_webhook(): void
    {
        Storage::fake('s3');

        Http::fake([
            'tempfile.aiquickdraw.com/*' => Http::response('FAKE_PNG_DATA', 200, ['Content-Type' => 'image/png']),
        ]);

        $author = Author::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'title' => 'Mi Planta Favorita',
            'cover_image' => null,
        ]);

        // Step 1: Job creates the request
        GenerateModelImage::dispatchSync($post, 'cover_image');

        // Get the generated taskId from the request
        $request = ImageGenerationRequest::query()
            ->where('targetable_type', Post::class)
            ->where('targetable_id', $post->id)
            ->first();

        $this->assertNotNull($request);
        $this->assertEquals('pending', $request->status);
        $this->assertStringStartsWith('task_', $request->external_id);

        // Step 2: Webhook arrives with real NanoBanana format
        $webhookPayload = MockBananaImageGenerator::getWebhookPayload($request->external_id);

        $response = $this->postWebhook($webhookPayload);
        $response->assertOk();

        // Step 3: Verify final state
        $post->refresh();
        $this->assertNotNull($post->cover_image);
        $this->assertStringContainsString('mi-planta-favorita', $post->cover_image);

        $request->refresh();
        $this->assertEquals('completed', $request->status);
    }

    public function test_webhook_without_image_url_returns_awaiting(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'cover_image' => null,
        ]);

        $taskId = 'pending-task-456';

        ImageGenerationRequest::query()->create([
            'external_id' => $taskId,
            'targetable_type' => Post::class,
            'targetable_id' => $post->id,
            'prompt' => 'Test prompt',
            'status' => 'pending',
            'metadata' => ['attribute' => 'cover_image'],
        ]);

        // Webhook without imageUrl (intermediate callback)
        $response = $this->postWebhook([
            'msg' => 'Processing...',
            'code' => 200,
            'data' => [
                'taskId' => $taskId,
            ],
        ]);

        $response->assertStatus(202)
            ->assertJson(['message' => 'Accepted - awaiting image URL']);

        // Request should be in processing state
        $this->assertDatabaseHas('image_generation_requests', [
            'external_id' => $taskId,
            'status' => 'processing',
        ]);
    }

    public function test_webhook_with_unknown_task_id_returns_202(): void
    {
        $response = $this->postWebhook([
            'taskId' => 'unknown-task-id',
            'imageUrl' => 'https://example.com/image.png',
        ]);

        $response->assertStatus(202)
            ->assertJson(['message' => 'No matching request found for taskId, ignoring']);
    }

    public function test_webhook_without_task_id_returns_422(): void
    {
        $response = $this->postWebhook([
            'imageUrl' => 'https://example.com/image.png',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Missing taskId in payload']);
    }

    public function test_job_is_queued_when_dispatched(): void
    {
        Queue::fake();

        $author = Author::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'cover_image' => null,
        ]);

        GenerateModelImage::dispatch($post, 'cover_image');

        Queue::assertPushed(GenerateModelImage::class, function ($job) use ($post) {
            return $job->model->id === $post->id
                && $job->attribute === 'cover_image';
        });
    }

    public function test_mock_generator_creates_unique_task_ids(): void
    {
        $generator = app(ImageGeneratorInterface::class);

        $taskId1 = $generator->generate('First prompt');
        $taskId2 = $generator->generate('Second prompt');

        $this->assertNotEquals($taskId1, $taskId2);
        $this->assertStringStartsWith('task_', $taskId1);
        $this->assertStringStartsWith('task_', $taskId2);
    }

    public function test_mock_generator_webhook_payload_matches_real_format(): void
    {
        $generator = app(ImageGeneratorInterface::class);
        $taskId = $generator->generate('Test prompt');

        $payload = MockBananaImageGenerator::getWebhookPayload($taskId);

        // Verify structure matches real NanoBanana format
        $this->assertArrayHasKey('msg', $payload);
        $this->assertArrayHasKey('code', $payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('taskId', $payload['data']);
        $this->assertArrayHasKey('info', $payload['data']);
        $this->assertArrayHasKey('resultImageUrl', $payload['data']['info']);

        $this->assertEquals($taskId, $payload['data']['taskId']);
    }
}
