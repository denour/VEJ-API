<?php

namespace Tests\Unit;

use App\Jobs\GenerateModelImage;
use App\Models\Author;
use App\Models\Post;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GenerateModelImagePromptTest extends TestCase
{
    private function postPrompt(Post $post): string
    {
        $job = new GenerateModelImage($post, 'cover_image');

        $method = new ReflectionMethod($job, 'generatePostPrompt');
        $method->setAccessible(true);

        return $method->invoke($job);
    }

    private function authorPrompt(Author $author): string
    {
        $job = new GenerateModelImage($author, 'avatar_url');

        $method = new ReflectionMethod($job, 'generateAuthorPrompt');
        $method->setAccessible(true);

        return $method->invoke($job);
    }

    public function test_post_cover_prompt_forbids_text_and_banner_layouts(): void
    {
        $post = new Post([
            'title' => 'Sustratos vivos para balcones urbanos',
            'excerpt' => 'Cómo convertir sustratos en biobandas para balcones.',
            'category' => 'Consejos',
        ]);

        $prompt = strtolower($this->postPrompt($post));

        $this->assertStringContainsString('photograph', $prompt);
        $this->assertStringContainsString('no text', $prompt);
        $this->assertStringContainsString('never render this text', $prompt);
        $this->assertStringContainsString('not an infographic', $prompt);
        $this->assertStringContainsString('not a banner', $prompt);

        // The old graphic-design framing that produced text banners must be gone.
        $this->assertStringNotContainsString('engaging design', $prompt);
        $this->assertStringNotContainsString('professional cover image for a blog post', $prompt);
    }

    public function test_author_prompt_is_ultra_realistic_with_no_text_and_no_3d(): void
    {
        $author = new Author(['name' => 'Clara Molina']);

        $prompt = strtolower($this->authorPrompt($author));

        // Must steer toward an authentic photograph, not CGI/3D.
        $this->assertStringContainsString('ultra-realistic', $prompt);
        $this->assertStringContainsString('photograph', $prompt);
        $this->assertStringContainsString('not a 3d render', $prompt);

        // The name may be used to infer appearance, but must never be rendered as text.
        $this->assertStringContainsString('never write the name', $prompt);
        $this->assertStringContainsString('no text', $prompt);

        // The old framing that produced 3D avatars with baked-in name captions must be gone.
        $this->assertStringNotContainsString('modern and friendly', $prompt);
        $this->assertStringNotContainsString('portrait style image', $prompt);
    }
}
