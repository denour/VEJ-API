<?php

namespace Tests\Feature;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Jobs\GenerateModelImage;
use App\Jobs\PublishToSocialMedia;
use App\Models\Author;
use App\Models\AuthorTopic;
use App\Models\Post;
use App\Services\AI\AuthorDescriptionGeneratorService;
use App\Services\AI\PersonaPromptBuilder;
use App\Services\AI\PostGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostGenerationRobustnessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Structure JSON followed by canned block text; enough for a 2-block post.
     *
     * @param  list<string>  $extraBlocks
     */
    private function cannedResponses(string $title, array $extraBlocks = ['Un párrafo.']): array
    {
        $structure = json_encode([
            'title' => $title,
            'excerpt' => 'Un excerpt concreto.',
            'category' => 'Cuidado',
            'tags' => ['a', 'b'],
            'blocks' => [
                ['type' => 'paragraph', 'description' => 'Intro'],
            ],
        ]);

        return array_merge([$structure], $extraBlocks);
    }

    private function makeService(array $textResponses, ImageGeneratorInterface $imageGenerator): PostGeneratorService
    {
        $queue = $textResponses;

        $text = $this->createMock(TextGeneratorInterface::class);
        $text->method('generate')->willReturnCallback(function () use (&$queue) {
            return array_shift($queue) ?? 'relleno';
        });

        return new PostGeneratorService(
            $text,
            $imageGenerator,
            new AuthorDescriptionGeneratorService($text),
            new PersonaPromptBuilder
        );
    }

    public function test_generate_post_does_not_dispatch_observer_image_jobs(): void
    {
        Queue::fake();

        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->method('getProviderName')->willReturn('test');
        $image->method('generate')->willReturn('posts/cover.png');

        $author = Author::factory()->create();
        $service = $this->makeService($this->cannedResponses('Post uno'), $image);

        $service->generatePost($author, null, ['status' => 'published']);

        // The observer path (queued GenerateModelImage) must NOT fire — the
        // service generates images inline, and a second async pass would double
        // the paid image spend and overwrite the cover.
        Queue::assertNotPushed(GenerateModelImage::class);
    }

    public function test_generate_post_generates_cover_exactly_once(): void
    {
        Queue::fake();

        $calls = 0;
        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->method('getProviderName')->willReturn('test');
        $image->method('generate')->willReturnCallback(function () use (&$calls) {
            $calls++;

            return 'posts/cover.png';
        });

        $author = Author::factory()->create();
        $service = $this->makeService($this->cannedResponses('Post dos'), $image);

        $service->generatePost($author, null, ['status' => 'published']);

        // One image call = the cover (the post has no image block).
        $this->assertSame(1, $calls);
    }

    public function test_published_slug_collision_does_not_overwrite_existing_post(): void
    {
        Queue::fake();

        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->method('getProviderName')->willReturn('test');
        $image->method('generate')->willReturn('posts/cover.png');

        $author = Author::factory()->create();

        $existing = Post::factory()->create([
            'author_id' => $author->id,
            'title' => 'Plantas para balcones',
            'slug' => 'plantas-para-balcones',
            'status' => 'published',
        ]);

        $service = $this->makeService($this->cannedResponses('Plantas para balcones'), $image);
        $new = $service->generatePost($author, null, ['status' => 'published']);

        // The published post must survive untouched, and the new one gets a
        // disambiguated slug instead of clobbering it.
        $this->assertNotSame($existing->id, $new->id);
        $this->assertNotSame('plantas-para-balcones', $new->slug);
        $this->assertStringStartsWith('plantas-para-balcones-', $new->slug);
        $this->assertDatabaseHas('posts', ['id' => $existing->id, 'slug' => 'plantas-para-balcones']);
    }

    public function test_draft_slug_collision_is_reused(): void
    {
        Queue::fake();

        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->method('getProviderName')->willReturn('test');
        $image->method('generate')->willReturn('posts/cover.png');

        $author = Author::factory()->create();

        $draft = Post::factory()->create([
            'author_id' => $author->id,
            'title' => 'Borrador del día',
            'slug' => 'borrador-del-dia',
            'status' => 'draft',
        ]);

        $service = $this->makeService($this->cannedResponses('Borrador del día'), $image);
        $regenerated = $service->generatePost($author, null, ['status' => 'published']);

        // A same-day re-run over a draft replaces it in place (same row).
        $this->assertSame($draft->id, $regenerated->id);
        $this->assertSame('borrador-del-dia', $regenerated->slug);
    }

    public function test_structure_retries_once_on_invalid_json(): void
    {
        Queue::fake();

        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->method('getProviderName')->willReturn('test');
        $image->method('generate')->willReturn('posts/cover.png');

        // First structure call returns garbage, second returns valid JSON.
        $responses = array_merge(
            ['no soy json {{{'],
            $this->cannedResponses('Recuperado tras reintento')
        );
        $service = $this->makeService($responses, $image);

        $author = Author::factory()->create();
        $post = $service->generatePost($author, null, ['status' => 'published']);

        $this->assertSame('Recuperado tras reintento', $post->title);
    }

    public function test_structure_throws_after_two_invalid_json_responses(): void
    {
        Queue::fake();

        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('generate')->willReturn('posts/cover.png');

        $service = $this->makeService(['basura', 'mas basura', 'y mas'], $image);
        $author = Author::factory()->create();

        $this->expectException(\RuntimeException::class);
        $service->generatePost($author, null, ['status' => 'published']);
    }

    public function test_daily_command_keeps_draft_and_skips_social_when_cover_fails(): void
    {
        Queue::fake();

        // Text: valid structure + block text. Image: always throws → cover stays null.
        $textQueue = $this->cannedResponses('Post sin portada');
        $text = $this->createMock(TextGeneratorInterface::class);
        $text->method('generate')->willReturnCallback(function () use (&$textQueue) {
            return array_shift($textQueue) ?? 'relleno';
        });
        $this->app->instance(TextGeneratorInterface::class, $text);

        $image = $this->createMock(ImageGeneratorInterface::class);
        $image->method('isSynchronous')->willReturn(true);
        $image->method('getProviderName')->willReturn('test');
        $image->method('generate')->willThrowException(new \RuntimeException('image API down'));
        $this->app->instance(ImageGeneratorInterface::class, $image);

        $author = Author::factory()->create(['is_active' => true]);
        AuthorTopic::factory()->count(3)->create(['author_id' => $author->id]);

        $this->artisan('posts:generate-daily')->assertExitCode(1);

        $post = Post::query()->latest('created_at')->first();
        $this->assertNotNull($post);
        $this->assertNull($post->cover_image);
        $this->assertSame('draft', $post->status);
        Queue::assertNotPushed(PublishToSocialMedia::class);
    }
}
