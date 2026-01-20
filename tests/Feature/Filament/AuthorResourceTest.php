<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AuthorResource;
use App\Filament\Resources\AuthorResource\Pages\CreateAuthor;
use App\Filament\Resources\AuthorResource\Pages\EditAuthor;
use App\Filament\Resources\AuthorResource\Pages\ListAuthors;
use App\Models\Author;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_render_list_authors_page(): void
    {
        $this->get(AuthorResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_can_list_authors(): void
    {
        $authors = Author::factory()->count(10)->create();

        Livewire::test(ListAuthors::class)
            ->assertCanSeeTableRecords($authors);
    }

    public function test_can_render_create_author_page(): void
    {
        $this->get(AuthorResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function test_avatar_url_field_is_hidden_on_create_page(): void
    {
        Livewire::test(CreateAuthor::class)
            ->assertFormFieldDoesNotExist('avatar_url');
    }

    public function test_can_create_author(): void
    {
        $newData = [
            'name' => 'Maria Rodriguez',
            'slug' => 'maria-rodriguez',
            'is_active' => true,
            'background_story' => 'A passionate gardener with 20 years of experience.',
            'personality_traits' => ['patient', 'knowledgeable', 'friendly'],
            'expertise_areas' => ['tropical plants', 'indoor gardening'],
            'sentence_style' => 'varied',
            'vocabulary_level' => 'conversational',
            'tone' => 'warm',
            'formality' => 'balanced',
        ];

        Livewire::test(CreateAuthor::class)
            ->fillForm($newData)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('authors', [
            'name' => 'Maria Rodriguez',
            'slug' => 'maria-rodriguez',
            'is_active' => true,
        ]);
    }

    public function test_can_validate_author_input(): void
    {
        Livewire::test(CreateAuthor::class)
            ->fillForm([
                'name' => '',
                'slug' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'slug' => 'required']);
    }

    public function test_slug_must_be_unique(): void
    {
        $author = Author::factory()->create(['slug' => 'existing-author']);

        Livewire::test(CreateAuthor::class)
            ->fillForm([
                'name' => 'New Author',
                'slug' => 'existing-author',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_can_render_edit_author_page(): void
    {
        $author = Author::factory()->create();

        $this->get(AuthorResource::getUrl('edit', ['record' => $author]))
            ->assertSuccessful();
    }

    public function test_avatar_url_field_is_visible_on_edit_page(): void
    {
        $author = Author::factory()->create();

        Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
            ->assertFormFieldExists('avatar_url');
    }

    public function test_can_retrieve_author_data_for_editing(): void
    {
        $author = Author::factory()->create();

        Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
            ->assertFormSet([
                'name' => $author->name,
                'slug' => $author->slug,
                'is_active' => $author->is_active,
            ]);
    }

    public function test_can_update_author(): void
    {
        $author = Author::factory()->create();

        Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Name',
                'slug' => 'updated-name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $author->refresh();

        $this->assertEquals('Updated Name', $author->name);
        $this->assertEquals('updated-name', $author->slug);
    }

    public function test_can_delete_author(): void
    {
        $author = Author::factory()->create();

        Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($author);
    }

    public function test_can_filter_authors_by_active_status(): void
    {
        $activeAuthors = Author::factory()->count(5)->create(['is_active' => true]);
        $inactiveAuthors = Author::factory()->count(3)->create(['is_active' => false]);

        Livewire::test(ListAuthors::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords($activeAuthors)
            ->assertCanNotSeeTableRecords($inactiveAuthors);
    }

    public function test_can_filter_authors_by_voice_bible(): void
    {
        $withVoiceBible = Author::factory()->count(3)->create(['voice_bible' => 'Sample voice bible content']);
        $withoutVoiceBible = Author::factory()->count(2)->create(['voice_bible' => null]);

        Livewire::test(ListAuthors::class)
            ->filterTable('has_voice_bible')
            ->assertCanSeeTableRecords($withVoiceBible)
            ->assertCanNotSeeTableRecords($withoutVoiceBible);
    }

    public function test_can_search_authors_by_name(): void
    {
        $author = Author::factory()->create(['name' => 'Unique Author Name']);
        Author::factory()->count(5)->create();

        Livewire::test(ListAuthors::class)
            ->searchTable('Unique Author Name')
            ->assertCanSeeTableRecords([$author]);
    }

    public function test_name_field_has_ai_assist_action(): void
    {
        Livewire::test(CreateAuthor::class)
            ->assertFormFieldExists('name')
            ->assertFormComponentActionExists('name', 'ai_assist_name');
    }

    public function test_background_story_field_has_ai_assist_action(): void
    {
        Livewire::test(CreateAuthor::class)
            ->assertFormComponentActionExists('background_story', 'ai_assist_background_story');
    }

    public function test_personality_traits_field_has_ai_assist_action(): void
    {
        Livewire::test(CreateAuthor::class)
            ->assertFormComponentActionExists('personality_traits', 'ai_assist_personality_traits');
    }

    public function test_edit_page_has_generate_voice_bible_action(): void
    {
        $author = Author::factory()->create([
            'background_story' => 'A passionate gardener',
        ]);

        Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
            ->assertActionExists('generate_voice_bible');
    }

    public function test_edit_page_has_preview_voice_action(): void
    {
        $author = Author::factory()->create();

        Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
            ->assertActionExists('preview_voice');
    }

    public function test_table_displays_author_avatar(): void
    {
        $author = Author::factory()->create([
            'name' => 'John Doe',
            'avatar_url' => 'https://example.com/avatar.jpg',
        ]);

        Livewire::test(ListAuthors::class)
            ->assertCanSeeTableRecords([$author])
            ->assertTableColumnExists('avatar_url');
    }

    public function test_table_displays_generation_stats(): void
    {
        $author = Author::factory()->create([
            'generation_stats' => [
                'posts_generated' => 5,
            ],
        ]);

        Livewire::test(ListAuthors::class)
            ->assertCanSeeTableRecords([$author])
            ->assertTableColumnExists('generation_stats.posts_generated');
    }

    public function test_slug_is_auto_generated_from_name(): void
    {
        Livewire::test(CreateAuthor::class)
            ->fillForm([
                'name' => 'Test Author Name',
            ])
            ->assertFormSet([
                'slug' => 'test-author-name',
            ]);
    }

    public function test_can_access_all_persona_tabs(): void
    {
        Livewire::test(CreateAuthor::class)
            ->assertFormExists()
            ->assertFormFieldExists('name') // Basic Info tab
            ->assertFormFieldExists('background_story') // Persona tab
            ->assertFormFieldExists('personality_traits')
            ->assertFormFieldExists('expertise_areas')
            ->assertFormFieldExists('sentence_style')
            ->assertFormFieldExists('vocabulary_level')
            ->assertFormFieldExists('tone')
            ->assertFormFieldExists('formality')
            ->assertFormFieldExists('catchphrases')
            ->assertFormFieldExists('quirks')
            ->assertFormFieldExists('recurring_topics')
            ->assertFormFieldExists('avoided_elements');
    }

    public function test_voice_bible_tab_is_hidden_on_create(): void
    {
        Livewire::test(CreateAuthor::class)
            ->assertFormFieldDoesNotExist('voice_bible')
            ->assertFormFieldDoesNotExist('sample_paragraph');
    }

    public function test_voice_bible_tab_is_visible_on_edit(): void
    {
        $author = Author::factory()->create();

        Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
            ->assertFormFieldExists('voice_bible')
            ->assertFormFieldExists('sample_paragraph');
    }
}
