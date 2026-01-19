<?php

namespace Tests\Feature;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Models\ImageGenerationRequest;
use App\Models\Post;
use App\Models\PostBlock;
use App\Services\AI\PostContentAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_can_generate_cover_image_request(): void
    {
        $mockImageGenerator = $this->createMock(ImageGeneratorInterface::class);
        $mockImageGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('test-task-id-123');

        $this->app->instance(ImageGeneratorInterface::class, $mockImageGenerator);

        $service = app(PostContentAssistantService::class);

        $result = $service->generateFieldContent(
            'cover_image',
            'Imagen destacada para: Cómo cultivar tomates',
            [
                'title' => 'Cómo cultivar tomates',
                'category' => 'Hortalizas',
                'post_id' => 'test-post-id',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['pending']);
        $this->assertStringContainsString('Generando imagen', $result['value']);
        $this->assertEquals('test-task-id-123', $result['taskId']);

        $this->assertDatabaseHas('image_generation_requests', [
            'external_id' => 'test-task-id-123',
            'targetable_type' => Post::class,
            'targetable_id' => 'test-post-id',
            'status' => 'pending',
        ]);

        $request = ImageGenerationRequest::where('external_id', 'test-task-id-123')->first();
        $this->assertEquals('cover_image', $request->metadata['attribute']);
        $this->assertEquals('16:9', $request->size);
    }

    public function test_service_can_generate_block_image_request(): void
    {
        $mockImageGenerator = $this->createMock(ImageGeneratorInterface::class);
        $mockImageGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('test-block-task-id-456');

        $this->app->instance(ImageGeneratorInterface::class, $mockImageGenerator);

        $service = app(PostContentAssistantService::class);

        $result = $service->generateFieldContent(
            'block_image',
            'Imagen ilustrativa para: Plantas de interior',
            [
                'title' => 'Plantas de interior',
                'post_id' => 'test-post-id',
                'block_id' => 'test-block-id',
                'block_content' => 'Las plantas de interior necesitan luz indirecta',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['pending']);

        $this->assertDatabaseHas('image_generation_requests', [
            'external_id' => 'test-block-task-id-456',
            'targetable_type' => PostBlock::class,
            'targetable_id' => 'test-block-id',
            'status' => 'pending',
        ]);

        $request = ImageGenerationRequest::where('external_id', 'test-block-task-id-456')->first();
        $this->assertEquals('data.url', $request->metadata['attribute']);
        $this->assertEquals('4:3', $request->size);
    }

    public function test_service_handles_image_generation_errors(): void
    {
        $mockImageGenerator = $this->createMock(ImageGeneratorInterface::class);
        $mockImageGenerator->expects($this->once())
            ->method('generate')
            ->willThrowException(new \Exception('API error'));

        $this->app->instance(ImageGeneratorInterface::class, $mockImageGenerator);

        $service = app(PostContentAssistantService::class);

        $result = $service->generateFieldContent(
            'cover_image',
            'Test prompt',
            ['post_id' => 'test-post-id']
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API error', $result['error']);
    }
}
