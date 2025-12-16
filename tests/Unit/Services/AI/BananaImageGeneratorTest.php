<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\BananaImageGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BananaImageGeneratorTest extends TestCase
{
    public function test_generate_success_stores_image_and_returns_url(): void
    {
        $this->createApplication();
        Storage::fake('s3');

        Http::fakeSequence()
            // Initial generation request
            ->push([
                'data' => [
                    'taskId' => 'abc123',
                ],
            ], 200)
            // Polling request
            ->push([
                'data' => [
                    'successFlag' => 1,
                    'response' => [
                        'resultImageUrl' => 'https://example.com/image.png',
                    ],
                ],
            ], 200)
            // Image download
            ->push('PNGDATA', 200);

        $generator = new BananaImageGenerator('test-api-key');

        $url = $generator->generate('A beautiful plant');

        $files = Storage::disk('s3')->allFiles('posts');
        $this->assertCount(1, $files);
        $this->assertStringContainsString(basename($files[0]), $url);
        $this->assertTrue(Storage::disk('s3')->exists($files[0]));
    }

    public function test_generate_throws_when_no_task_id_returned(): void
    {
        $this->createApplication();
        Storage::fake('s3');

        Http::fakeSequence()
            ->push([
                'data' => [],
                'message' => 'Invalid request payload',
            ], 200);

        $generator = new BananaImageGenerator('test-api-key');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('taskId');

        $generator->generate('A plant');
    }

    public function test_pro_payload_includes_additional_fields(): void
    {
        // Switch app environment to production to hit generate-pro
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_ENV'] = 'production';
        $this->refreshApplication();

        Storage::fake('s3');

        config()->set('services.banana.callback_url', 'https://example-callback.com');

        Http::fake(function ($request) {
            $url = (string) $request->url();
            if (str_contains($url, '/nanobanana/generate-pro')) {
                $json = $request->data();
                // Assert extra fields exist
                \PHPUnit\Framework\Assert::assertNotNull($json['prompt'] ?? null);
                \PHPUnit\Framework\Assert::assertSame('TEXTTOIMAGE', $json['type'] ?? null);
                \PHPUnit\Framework\Assert::assertArrayHasKey('image_size', $json);
                \PHPUnit\Framework\Assert::assertArrayHasKey('resolution', $json);
                \PHPUnit\Framework\Assert::assertArrayHasKey('aspectRatio', $json);
                \PHPUnit\Framework\Assert::assertArrayHasKey('imageUrls', $json);
                \PHPUnit\Framework\Assert::assertSame('https://example-callback.com', $json['callBackUrl'] ?? null);

                return Http::response([
                    'data' => [
                        'taskId' => 'pro123',
                    ],
                ], 200);
            }

            if (str_contains($url, '/nanobanana/record-info')) {
                return Http::response([
                    'data' => [
                        'successFlag' => 1,
                        'response' => [
                            'resultImageUrl' => 'https://example.com/pro.png',
                        ],
                    ],
                ], 200);
            }

            if ($url === 'https://example.com/pro.png') {
                return Http::response('PNGDATA', 200);
            }

            return Http::response([], 404);
        });

        $generator = new BananaImageGenerator('test-api-key');

        $url = $generator->generate('A beautiful plant - pro', [
            'width' => 1792,
            'height' => 1024,
        ]);

        $files = Storage::disk('s3')->allFiles('posts');
        $this->assertCount(1, $files);
        $this->assertStringContainsString(basename($files[0]), $url);
        $this->assertTrue(Storage::disk('s3')->exists($files[0]));
    }
}
