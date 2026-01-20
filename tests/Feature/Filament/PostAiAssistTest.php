<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Models\Author;
use App\Models\User;
use App\Services\AI\PostContentAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PostAiAssistTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Author $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->author = Author::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_ai_assist_can_generate_post_title(): void
    {
        // Mock the PostContentAssistantService
        $mock = Mockery::mock(PostContentAssistantService::class);
        $mock->shouldReceive('generateFieldContent')
            ->once()
            ->with('title', Mockery::type('string'), Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'type' => 'text',
                'value' => 'Cómo Cultivar Rosas en tu Jardín',
            ]);

        $this->app->instance(PostContentAssistantService::class, $mock);

        Livewire::test(CreatePost::class)
            ->callFormComponentAction('title', 'ai_assist_title')
            ->assertNotified()
            ->assertFormSet([
                'title' => 'Cómo Cultivar Rosas en tu Jardín',
                'slug' => 'como-cultivar-rosas-en-tu-jardin',
            ]);
    }

    public function test_ai_assist_can_generate_excerpt(): void
    {
        // Mock the PostContentAssistantService
        $mock = Mockery::mock(PostContentAssistantService::class);
        $mock->shouldReceive('generateFieldContent')
            ->once()
            ->with('excerpt', Mockery::type('string'), Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'type' => 'text',
                'value' => 'Aprende las mejores técnicas para cultivar rosas hermosas y saludables en tu jardín.',
            ]);

        $this->app->instance(PostContentAssistantService::class, $mock);

        Livewire::test(CreatePost::class)
            ->callFormComponentAction('excerpt', 'ai_assist_excerpt')
            ->assertNotified()
            ->assertFormSet([
                'excerpt' => 'Aprende las mejores técnicas para cultivar rosas hermosas y saludables en tu jardín.',
            ]);
    }

    public function test_ai_assist_can_generate_tags(): void
    {
        // Mock the PostContentAssistantService
        $mock = Mockery::mock(PostContentAssistantService::class);
        $mock->shouldReceive('generateFieldContent')
            ->once()
            ->with('tags', Mockery::type('string'), Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'type' => 'array',
                'value' => ['rosas', 'jardinería', 'flores', 'cultivo', 'jardín'],
            ]);

        $this->app->instance(PostContentAssistantService::class, $mock);

        Livewire::test(CreatePost::class)
            ->callFormComponentAction('tags', 'ai_assist_tags')
            ->assertNotified()
            ->assertFormSet([
                'tags' => ['rosas', 'jardinería', 'flores', 'cultivo', 'jardín'],
            ]);
    }

    public function test_ai_assist_shows_error_notification_on_failure(): void
    {
        // Mock the PostContentAssistantService to return an error
        $mock = Mockery::mock(PostContentAssistantService::class);
        $mock->shouldReceive('generateFieldContent')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'API rate limit exceeded',
            ]);

        $this->app->instance(PostContentAssistantService::class, $mock);

        Livewire::test(CreatePost::class)
            ->callFormComponentAction('title', 'ai_assist_title')
            ->assertNotified();
    }

    public function test_ai_assist_uses_existing_form_data_as_context(): void
    {
        // Mock the PostContentAssistantService
        $mock = Mockery::mock(PostContentAssistantService::class);
        $mock->shouldReceive('generateFieldContent')
            ->once()
            ->with(
                'excerpt',
                Mockery::type('string'),
                Mockery::type('array')
            )
            ->andReturn([
                'success' => true,
                'type' => 'text',
                'value' => 'Un extracto sobre rosas.',
            ]);

        $this->app->instance(PostContentAssistantService::class, $mock);

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Cómo Cultivar Rosas',
                'category' => 'Flores',
                'author_id' => $this->author->id,
            ])
            ->callFormComponentAction('excerpt', 'ai_assist_excerpt')
            ->assertNotified()
            ->assertFormSet([
                'excerpt' => 'Un extracto sobre rosas.',
            ]);
    }

    public function test_ai_assist_buttons_exist_on_form(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormExists()
            ->assertFormComponentActionExists('title', 'ai_assist_title')
            ->assertFormComponentActionExists('excerpt', 'ai_assist_excerpt')
            ->assertFormComponentActionExists('tags', 'ai_assist_tags');
    }
}
