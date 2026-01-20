<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AuthorResource\Pages\CreateAuthor;
use App\Models\User;
use App\Services\AI\PersonaFieldAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AuthorAiAssistTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_ai_assist_modal_can_be_opened_for_name_field(): void
    {
        Livewire::test(CreateAuthor::class)
            ->mountFormComponentAction('name', 'ai_assist_name')
            ->assertFormComponentActionMounted('name', 'ai_assist_name')
            ->assertFormComponentActionDataSet([]);
    }

    public function test_ai_assist_can_generate_name_suggestion(): void
    {
        // Mock the PersonaFieldAssistantService
        $mock = Mockery::mock(PersonaFieldAssistantService::class);
        $mock->shouldReceive('generateFieldSuggestion')
            ->once()
            ->with('name', 'A warm gardening expert', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'type' => 'text',
                'value' => 'Maria Rodriguez',
            ]);

        $this->app->instance(PersonaFieldAssistantService::class, $mock);

        Livewire::test(CreateAuthor::class)
            ->mountFormComponentAction('name', 'ai_assist_name')
            ->setFormComponentActionData([
                'prompt' => 'A warm gardening expert',
            ])
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors()
            ->assertFormSet([
                'name' => 'Maria Rodriguez',
            ]);
    }

    public function test_ai_assist_can_generate_personality_traits(): void
    {
        // Mock the PersonaFieldAssistantService
        $mock = Mockery::mock(PersonaFieldAssistantService::class);
        $mock->shouldReceive('generateFieldSuggestion')
            ->once()
            ->with('personality_traits', 'Patient and nurturing', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'type' => 'array',
                'value' => ['patient', 'nurturing', 'knowledgeable'],
            ]);

        $this->app->instance(PersonaFieldAssistantService::class, $mock);

        Livewire::test(CreateAuthor::class)
            ->mountFormComponentAction('personality_traits', 'ai_assist_personality_traits')
            ->setFormComponentActionData([
                'prompt' => 'Patient and nurturing',
            ])
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors()
            ->assertFormSet([
                'personality_traits' => ['patient', 'nurturing', 'knowledgeable'],
            ]);
    }

    public function test_ai_assist_shows_error_notification_on_failure(): void
    {
        // Mock the PersonaFieldAssistantService to return an error
        $mock = Mockery::mock(PersonaFieldAssistantService::class);
        $mock->shouldReceive('generateFieldSuggestion')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'API rate limit exceeded',
            ]);

        $this->app->instance(PersonaFieldAssistantService::class, $mock);

        Livewire::test(CreateAuthor::class)
            ->mountFormComponentAction('name', 'ai_assist_name')
            ->setFormComponentActionData([
                'prompt' => 'Test prompt',
            ])
            ->callMountedFormComponentAction()
            ->assertNotified();
    }

    public function test_ai_assist_uses_existing_form_data_as_context(): void
    {
        // Mock the PersonaFieldAssistantService
        $mock = Mockery::mock(PersonaFieldAssistantService::class);
        $mock->shouldReceive('generateFieldSuggestion')
            ->once()
            ->with(
                'background_story',
                'Tell me about their experience',
                Mockery::on(function ($context) {
                    return isset($context['name']) && $context['name'] === 'John Doe';
                })
            )
            ->andReturn([
                'success' => true,
                'type' => 'text',
                'value' => 'John Doe has 20 years of gardening experience...',
            ]);

        $this->app->instance(PersonaFieldAssistantService::class, $mock);

        Livewire::test(CreateAuthor::class)
            ->fillForm([
                'name' => 'John Doe',
            ])
            ->mountFormComponentAction('background_story', 'ai_assist_background_story')
            ->setFormComponentActionData([
                'prompt' => 'Tell me about their experience',
            ])
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors();
    }
}
