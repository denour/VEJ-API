<?php

namespace Tests\Feature;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Post;
use App\Models\PostBlock;
use App\Services\Social\SocialMediaPublisher;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pure-logic tests for the social copy / caption building. These avoid the
 * database (the project's post-related suite has a pre-existing migration FK
 * issue — authors.id is bigint in the live local DB but ulid in the model)
 * by exercising the private builders directly with an unsaved Post, injecting
 * its `blocks` relation in-memory via setRelation() where needed.
 */
class SocialMediaPublisherTest extends TestCase
{
    private const COPY_JSON = '{"social_hook":"Tu balcon puede ser un jardin","fb_body":"Te contamos como transformar tu balcon en un huerto vivo.","ig_body":"Tu balcon tambien puede florecer 🌱🪴","carousel_tips":["Riega temprano para evitar hongos","Rota tus macetas cada semana","Usa composta casera siempre"]}';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'social.blog_url' => 'https://vidaeneljardin.com',
            'social.facebook.page_id' => 'PAGE123',
            'social.facebook.access_token' => 'TOKEN',
            'social.instagram.account_id' => 'IG123',
        ]);
    }

    private function publisher(?TextGeneratorInterface $text = null, ?ImageGeneratorInterface $image = null): SocialMediaPublisher
    {
        return new SocialMediaPublisher(
            $image ?? $this->createMock(ImageGeneratorInterface::class),
            $text ?? $this->createMock(TextGeneratorInterface::class),
        );
    }

    private function textGenerator(string $json): TextGeneratorInterface
    {
        $mock = $this->createMock(TextGeneratorInterface::class);
        $mock->method('getProviderName')->willReturn('test');
        $mock->method('generate')->willReturn($json);

        return $mock;
    }

    private function invoke(SocialMediaPublisher $publisher, string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod($publisher, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($publisher, ...$args);
    }

    private function samplePost(): Post
    {
        return new Post([
            'title' => 'Sustratos vivos: biobandas para balcones urbanos',
            'excerpt' => 'Como convertir sustratos en biobandas para balcones.',
            'slug' => 'sustratos-vivos-balcones',
            'category' => 'Consejos',
            'tags' => ['sustratos', 'balcones', 'huerto urbano'],
        ]);
    }

    public function test_generate_social_copy_parses_per_platform_fields(): void
    {
        $publisher = $this->publisher($this->textGenerator(self::COPY_JSON));

        $copy = $this->invoke($publisher, 'generateSocialCopy', $this->samplePost());

        $this->assertSame('Tu balcon puede ser un jardin', $copy['social_hook']);
        $this->assertStringContainsString('transformar tu balcon', $copy['fb_body']);
        $this->assertStringContainsString('florecer', $copy['ig_body']);
        $this->assertCount(3, $copy['carousel_tips']);
        $this->assertSame('Riega temprano para evitar hongos', $copy['carousel_tips'][0]);
    }

    public function test_generate_social_copy_tolerates_markdown_fences(): void
    {
        $fenced = "```json\n".self::COPY_JSON."\n```";
        $publisher = $this->publisher($this->textGenerator($fenced));

        $copy = $this->invoke($publisher, 'generateSocialCopy', $this->samplePost());

        $this->assertSame('Tu balcon puede ser un jardin', $copy['social_hook']);
    }

    public function test_generate_social_copy_falls_back_when_response_is_not_json(): void
    {
        $publisher = $this->publisher($this->textGenerator('totally not json'));
        $post = $this->samplePost();

        $copy = $this->invoke($publisher, 'generateSocialCopy', $post);

        // Hook falls back to a trimmed title; bodies fall back to the excerpt.
        $this->assertNotSame('', $copy['social_hook']);
        $this->assertSame($post->excerpt, $copy['fb_body']);
        $this->assertSame($post->excerpt, $copy['ig_body']);
        $this->assertSame([], $copy['carousel_tips']);
    }

    public function test_facebook_caption_has_clickable_url_and_few_hashtags(): void
    {
        $caption = $this->invoke($this->publisher(), 'buildFacebookCaption', $this->samplePost(), 'Cuerpo de Facebook');

        $this->assertStringContainsString('Cuerpo de Facebook', $caption);
        $this->assertStringContainsString('https://vidaeneljardin.com/blog/sustratos-vivos-balcones', $caption);
        $this->assertStringContainsString('#VidaEnElJardin', $caption);

        // Facebook stays lean: at most 2 post tags + 1 brand tag = 3 hashtags.
        $this->assertLessThanOrEqual(3, substr_count($caption, '#'));
    }

    public function test_instagram_caption_points_to_bio_without_inline_url(): void
    {
        $caption = $this->invoke($this->publisher(), 'buildInstagramCaption', $this->samplePost(), 'Cuerpo de Instagram');

        $this->assertStringContainsString('Cuerpo de Instagram', $caption);
        $this->assertStringContainsString('bio', $caption);
        $this->assertStringNotContainsString('https://vidaeneljardin.com', $caption);
        $this->assertStringContainsString('#VidaEnElJardin', $caption);

        // Instagram leans into hashtags (post tags + 3 brand tags).
        $this->assertGreaterThan(3, substr_count($caption, '#'));
    }

    public function test_async_social_hero_uses_the_existing_cover_without_requesting_a_task(): void
    {
        // Async generation returns a task ID, so use the completed cover instead.
        $imageMock = $this->createMock(ImageGeneratorInterface::class);
        $imageMock->method('isSynchronous')->willReturn(false);
        $imageMock->expects($this->never())->method('generate');

        $post = $this->samplePost();
        $post->cover_image = 'https://cdn.example.com/covers/mi-post.png';

        \Illuminate\Support\Facades\Storage::fake('s3');
        \Illuminate\Support\Facades\Http::fake([
            'https://cdn.example.com/covers/*' => \Illuminate\Support\Facades\Http::response($this->solidPng()),
        ]);

        $slides = $this->invoke($this->publisher(null, $imageMock), 'generateSocialSlides', $post, 'Tu balcon puede ser un jardin', []);

        // No tips requested → tipBackgroundUrls() short-circuits, never touching
        // the `blocks` relation → hero only.
        $this->assertCount(1, $slides);
        $this->assertStringContainsString('social/card-', $slides[0]);
    }

    public function test_social_hero_requests_a_portrait_even_when_the_blog_has_a_cover(): void
    {
        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->expects($this->once())->method('generate')
            ->with($this->callback(fn (string $prompt): bool => str_contains($prompt, 'NO text')),
                $this->callback(fn (array $options): bool => ($options['aspectRatio'] ?? null) === '4:5'))
            ->willReturn('https://cdn.example.com/social/portrait.png');
        $post = $this->samplePost();
        $post->cover_image = 'https://cdn.example.com/covers/blog.png';
        \Illuminate\Support\Facades\Storage::fake('s3');
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake([
            'https://cdn.example.com/social/portrait.png' => \Illuminate\Support\Facades\Http::response($this->solidPng()),
        ]);

        $slides = $this->invoke($this->publisher(null, $image), 'generateSocialSlides', $post, 'Tu balcón también puede florecer', [], 'florecer');

        $this->assertCount(1, $slides);
        $this->assertStringContainsString('social/card-', $slides[0]);
        $this->assertSame('https://cdn.example.com/covers/blog.png', $post->cover_image);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request): bool => $request->url() === 'https://cdn.example.com/social/portrait.png');
    }

    public function test_failed_portrait_generation_keeps_a_composed_cover(): void
    {
        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->expects($this->once())->method('generate')->willThrowException(new \RuntimeException('Generation unavailable'));
        $post = $this->samplePost();
        $post->cover_image = 'https://cdn.example.com/covers/blog.png';
        \Illuminate\Support\Facades\Storage::fake('s3');
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake([
            'https://cdn.example.com/covers/blog.png' => \Illuminate\Support\Facades\Http::response($this->solidPng()),
        ]);

        $slides = $this->invoke($this->publisher(null, $image), 'generateSocialSlides', $post, 'Tu balcón también puede florecer', []);

        $this->assertCount(1, $slides);
        $this->assertStringContainsString('social/card-', $slides[0]);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request): bool => $request->url() === $post->cover_image);
    }

    public function test_social_generation_limits_external_request_time(): void
    {
        $textOptions = [];
        $imageOptions = [];
        $text = $this->createMock(TextGeneratorInterface::class);
        $text->method('generate')->willReturnCallback(function (string $prompt, array $options) use (&$textOptions): string {
            $textOptions = $options;

            return self::COPY_JSON;
        });
        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->method('generate')->willReturnCallback(function (string $prompt, array $options) use (&$imageOptions): string {
            $imageOptions = $options;

            return 'https://cdn.example.com/social/portrait.png';
        });
        \Illuminate\Support\Facades\Storage::fake('s3');
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake([
            'https://cdn.example.com/social/portrait.png' => \Illuminate\Support\Facades\Http::response($this->solidPng()),
        ]);
        $publisher = $this->publisher($text, $image);
        $post = $this->samplePost();
        $copy = $this->invoke($publisher, 'generateSocialCopy', $post);
        $slides = $this->invoke($publisher, 'generateSocialSlides', $post, $copy['social_hook'], []);

        $this->assertCount(1, $slides);
        $this->assertSame(30, $textOptions['timeout'] ?? null);
        $this->assertSame(1, $textOptions['attempts'] ?? null);
        $this->assertSame(120, $imageOptions['timeout'] ?? null);
        $this->assertSame(1, $imageOptions['attempts'] ?? null);
        $this->assertSame(10, $imageOptions['download_timeout'] ?? null);
    }

    public function test_async_provider_without_cover_never_publishes_a_task_id(): void
    {
        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(false);
        $image->expects($this->never())->method('generate');
        \Illuminate\Support\Facades\Http::preventStrayRequests();

        $slides = $this->invoke($this->publisher(null, $image), 'generateSocialSlides', $this->samplePost(), 'Tu balcón puede florecer', []);

        $this->assertSame([], $slides);
    }

    public function test_malformed_copy_fields_use_safe_article_text(): void
    {
        $publisher = $this->publisher($this->textGenerator('{"social_hook":[],"social_hook_accent":[],"fb_body":{},"ig_body":false,"carousel_tips":{}}'));
        $post = $this->samplePost();

        $copy = $this->invoke($publisher, 'generateSocialCopy', $post);

        $this->assertNotSame('', $copy['social_hook']);
        $this->assertSame('', $copy['social_hook_accent']);
        $this->assertSame($post->excerpt, $copy['fb_body']);
        $this->assertSame($post->excerpt, $copy['ig_body']);
        $this->assertSame([], $copy['carousel_tips']);
    }

    public function test_failed_card_storage_never_returns_a_raw_photo(): void
    {
        $post = $this->samplePost();
        $post->cover_image = 'https://cdn.example.com/covers/blog.png';
        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->method('generate')->willReturn('https://cdn.example.com/social/portrait.png');
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response($this->solidPng()));
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('put')->times(3)->andReturn(false);
        \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $slides = $this->invoke($this->publisher(null, $image), 'generateSocialSlides', $post, 'Tu balcón puede florecer', []);

        $this->assertSame([], $slides);
    }

    public function test_social_image_prompt_forbids_ai_rendered_text(): void
    {
        // The hook is drawn by GD, not the model — so the background prompt must
        // NOT ask the model to render the hook, and must forbid any text.
        $captured = '';
        $imageMock = $this->createMock(ImageGeneratorInterface::class);
        $imageMock->method('isSynchronous')->willReturn(true);
        $imageMock->method('getProviderName')->willReturn('test');
        $imageMock->method('generate')->willReturnCallback(function (string $prompt) use (&$captured) {
            $captured = $prompt;

            return 'https://cdn.example.com/social/bg.png';
        });

        \Illuminate\Support\Facades\Storage::fake('s3');
        \Illuminate\Support\Facades\Http::fake([
            'https://cdn.example.com/*' => \Illuminate\Support\Facades\Http::response($this->solidPng()),
        ]);

        $slides = $this->invoke($this->publisher(null, $imageMock), 'generateSocialSlides', $this->samplePost(), 'Tu balcon puede ser un jardin', []);

        // Prompt drives the background only: no hook text, and text is forbidden.
        $this->assertStringNotContainsString('Tu balcon puede ser un jardin', $captured);
        $this->assertStringContainsString('NO text', $captured);

        // The returned URL is the composited card we stored, not the raw background.
        $this->assertStringContainsString('social/card-', $slides[0]);
    }

    public function test_social_slides_hero_keeps_branding_when_background_is_invalid(): void
    {
        $imageMock = $this->createMock(ImageGeneratorInterface::class);
        $imageMock->method('isSynchronous')->willReturn(true);
        $imageMock->method('getProviderName')->willReturn('test');
        $imageMock->method('generate')->willReturn('https://cdn.example.com/social/bg.png');

        \Illuminate\Support\Facades\Storage::fake('s3');
        // An invalid image must never be published as the final social card.
        \Illuminate\Support\Facades\Http::fake([
            'https://cdn.example.com/*' => \Illuminate\Support\Facades\Http::response('not-an-image'),
        ]);

        $slides = $this->invoke($this->publisher(null, $imageMock), 'generateSocialSlides', $this->samplePost(), 'Tu balcon puede ser un jardin', []);

        $this->assertCount(1, $slides);
        $this->assertStringContainsString('social/card-', $slides[0]);
    }

    /**
     * Build an unsaved Post with an in-memory `blocks` relation, so
     * tipBackgroundUrls() (which reads `$post->blocks` by property access)
     * never touches the database.
     */
    private function postWithInlineImageBlocks(array $imageUrls): Post
    {
        $post = $this->samplePost();
        $post->cover_image = 'https://cdn.example.com/covers/mi-post.png';

        $blocks = collect($imageUrls)->values()->map(function (string $url, int $order) {
            return new PostBlock([
                'type' => 'image',
                'order' => $order,
                'data' => ['url' => $url],
            ]);
        });

        // A pending image block (no url yet) mixed in — must be skipped, not
        // crash the composer.
        $blocks->push(new PostBlock(['type' => 'image', 'order' => count($imageUrls), 'data' => ['alt' => 'sin url todavía']]));

        $post->setRelation('blocks', Collection::make($blocks));

        return $post;
    }

    public function test_social_slides_adds_tip_slides_from_post_inline_images(): void
    {
        $post = $this->postWithInlineImageBlocks([
            'https://cdn.example.com/inline/one.png',
            'https://cdn.example.com/inline/two.png',
        ]);

        \Illuminate\Support\Facades\Storage::fake('s3');
        \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response($this->solidPng()));

        $tips = ['Riega temprano para evitar hongos', 'Rota tus macetas cada semana'];
        $slides = $this->invoke($this->publisher(), 'generateSocialSlides', $post, 'Tu balcon puede ser un jardin', $tips);

        // Hero + 2 tip slides (the pending block contributed no background).
        $this->assertCount(3, $slides);
        foreach ($slides as $slide) {
            $this->assertStringContainsString('social/card-', $slide);
        }
    }

    public function test_social_slides_stops_at_however_many_inline_images_exist(): void
    {
        $post = $this->postWithInlineImageBlocks(['https://cdn.example.com/inline/one.png']);

        \Illuminate\Support\Facades\Storage::fake('s3');
        \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response($this->solidPng()));

        $tips = ['Riega temprano para evitar hongos', 'Rota tus macetas cada semana', 'Usa composta casera siempre'];
        $slides = $this->invoke($this->publisher(), 'generateSocialSlides', $post, 'Tu balcon puede ser un jardin', $tips);

        // Only 1 inline image available → hero + 1 tip slide, no more.
        $this->assertCount(2, $slides);
    }

    public function test_social_slides_hero_only_when_post_has_no_inline_images(): void
    {
        $post = $this->postWithInlineImageBlocks([]);

        \Illuminate\Support\Facades\Storage::fake('s3');
        \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response($this->solidPng()));

        $tips = ['Riega temprano para evitar hongos'];
        $slides = $this->invoke($this->publisher(), 'generateSocialSlides', $post, 'Tu balcon puede ser un jardin', $tips);

        $this->assertCount(1, $slides);
    }

    private function solidPng(int $size = 256): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 90, 60));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
