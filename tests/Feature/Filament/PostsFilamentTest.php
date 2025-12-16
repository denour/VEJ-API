<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PostsFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('ecommerce');
    }

    public function test_list_shows_records_and_can_search(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $records = Post::factory()->count(3)->create();

        Livewire::test(ListPosts::class)
            ->assertCanSeeTableRecords($records)
            ->searchTable($records->first()->title)
            ->assertCanSeeTableRecords($records->take(1))
            ->assertCanNotSeeTableRecords($records->skip(1));
    }

    public function test_can_create_post(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $author = \App\Models\Author::factory()->create();

        $payload = [
            'title' => 'Nuevo Post',
            'slug' => 'nuevo-post',
            'excerpt' => 'Intro...',
            'content' => [],
            'list' => [],
            'category' => 'Cuidado',
            'author_id' => $author->id,
            'status' => 'draft',
        ];

        Livewire::test(CreatePost::class)
            ->fillForm($payload)
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(Post::class, [
            'title' => 'Nuevo Post',
            'slug' => 'nuevo-post',
            'category' => 'Cuidado',
            'status' => 'draft',
            'author_id' => $author->id,
        ]);
    }
}
