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

    public function test_social_slides_hero_uses_post_cover_as_background(): void
    {
        // With a cover present the model must NOT be called — the cover is the bg.
        $imageMock = $this->createMock(ImageGeneratorInterface::class);
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

    public function test_social_slides_hero_falls_back_to_background_when_overlay_fails(): void
    {
        $imageMock = $this->createMock(ImageGeneratorInterface::class);
        $imageMock->method('isSynchronous')->willReturn(true);
        $imageMock->method('getProviderName')->willReturn('test');
        $imageMock->method('generate')->willReturn('https://cdn.example.com/social/bg.png');

        \Illuminate\Support\Facades\Storage::fake('s3');
        // Non-image bytes → the composer throws → we fall back to the background URL.
        \Illuminate\Support\Facades\Http::fake([
            'https://cdn.example.com/*' => \Illuminate\Support\Facades\Http::response('not-an-image'),
        ]);

        $slides = $this->invoke($this->publisher(null, $imageMock), 'generateSocialSlides', $this->samplePost(), 'Tu balcon puede ser un jardin', []);

        $this->assertSame(['https://cdn.example.com/social/bg.png'], $slides);
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
