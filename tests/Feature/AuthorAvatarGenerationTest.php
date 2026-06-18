<?php

namespace Tests\Feature;

use App\Jobs\GenerateModelImage;
use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthorAvatarGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_author_without_avatar_queues_image_generation(): void
    {
        Queue::fake();

        $author = Author::factory()->create(['avatar_url' => null]);

        Queue::assertPushed(GenerateModelImage::class, function ($job) use ($author) {
            return $job->model->id === $author->id
                && $job->attribute === 'avatar_url';
        });
    }

    public function test_creating_an_author_with_avatar_does_not_queue_generation(): void
    {
        Queue::fake();

        Author::factory()->create(['avatar_url' => 'https://example.com/manual-avatar.png']);

        Queue::assertNotPushed(GenerateModelImage::class);
    }
}
