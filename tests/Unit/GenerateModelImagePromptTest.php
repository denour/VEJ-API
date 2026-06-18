<?php

namespace Tests\Unit;

use App\Jobs\GenerateModelImage;
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
}
