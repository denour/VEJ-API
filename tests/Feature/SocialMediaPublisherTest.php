<?php

namespace Tests\Feature;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Post;
use App\Services\Social\SocialMediaPublisher;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pure-logic tests for the social copy / caption building. These avoid the
 * database (the project's post-related suite has a pre-existing migration FK
 * issue) by exercising the private builders directly with an unsaved Post.
 */
class SocialMediaPublisherTest extends TestCase
{
    private const COPY_JSON = '{"social_hook":"Tu balcon puede ser un jardin","fb_body":"Te contamos como transformar tu balcon en un huerto vivo.","ig_body":"Tu balcon tambien puede florecer 🌱🪴"}';

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

    public function test_social_image_prompt_uses_hook_not_seo_title(): void
    {
        $captured = '';
        $imageMock = $this->createMock(ImageGeneratorInterface::class);
        $imageMock->method('isSynchronous')->willReturn(true);
        $imageMock->method('getProviderName')->willReturn('test');
        $imageMock->method('generate')->willReturnCallback(function (string $prompt) use (&$captured) {
            $captured = $prompt;

            return 'https://cdn.example.com/social/card.png';
        });

        $url = $this->invoke($this->publisher(null, $imageMock), 'generateSocialImage', $this->samplePost(), 'Tu balcon puede ser un jardin');

        $this->assertSame('https://cdn.example.com/social/card.png', $url);
        $this->assertStringContainsString('Tu balcon puede ser un jardin', $captured);
        $this->assertStringNotContainsString('Sustratos vivos: biobandas', $captured);
    }
}
