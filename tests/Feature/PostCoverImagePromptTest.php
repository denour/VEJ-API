<?php

namespace Tests\Feature;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Models\Author;
use App\Models\Post;
use App\Services\AI\PostGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class PostCoverImagePromptTest extends TestCase
{
    use RefreshDatabase;

    private function captureCoverPrompt(Post $post): string
    {
        $captured = '';

        $mockImageGenerator = $this->createMock(ImageGeneratorInterface::class);
        $mockImageGenerator->method('isSynchronous')->willReturn(true);
        $mockImageGenerator->method('getProviderName')->willReturn('test');
        $mockImageGenerator->method('generate')
            ->willReturnCallback(function (string $prompt) use (&$captured) {
                $captured = $prompt;

                return 'posts/test-cover.png';
            });

        $this->app->instance(ImageGeneratorInterface::class, $mockImageGenerator);

        $service = app(PostGeneratorService::class);

        $method = new ReflectionMethod($service, 'generateCoverImage');
        $method->setAccessible(true);
        $method->invoke($service, $post, $post->title, $post->excerpt ?? '');

        return $captured;
    }

    public function test_cover_prompt_forbids_text_and_banner_layouts(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'title' => 'Sustratos vivos para balcones',
            'excerpt' => 'Cómo convertir sustratos en biobandas para balcones urbanos.',
            'cover_image' => null,
        ]);

        $prompt = $this->captureCoverPrompt($post);

        $this->assertNotSame('', $prompt, 'The cover image prompt should have been generated.');

        $lower = strtolower($prompt);

        // It must explicitly forbid rendered text.
        $this->assertStringContainsString('no text', $lower);

        // It must NOT instruct the model to build a banner/cover layout (the bug).
        $this->assertStringNotContainsString('works well as a banner', $lower);
        $this->assertStringNotContainsString('banner/cover', $lower);

        // It must steer toward a clean photograph, not a graphic-design layout.
        $this->assertStringContainsString('photograph', $lower);
        $this->assertStringContainsString('not an infographic', $lower);
    }

    public function test_cover_prompt_keeps_excerpt_as_inspiration_only(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'title' => 'Riego eficiente en azoteas',
            'excerpt' => 'Calendario mensual de riego para huertos en azoteas.',
            'cover_image' => null,
        ]);

        $prompt = $this->captureCoverPrompt($post);

        // The excerpt may appear, but only framed as inspiration with an explicit
        // instruction not to render it as text in the image.
        $this->assertStringContainsString('never render this text', strtolower($prompt));
    }
}
