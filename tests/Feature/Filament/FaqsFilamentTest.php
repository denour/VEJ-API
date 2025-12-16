<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\FaqResource\Pages\CreateFaq;
use App\Filament\Resources\FaqResource\Pages\ListFaqs;
use App\Models\Faq;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FaqsFilamentTest extends TestCase
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

        $records = Faq::factory()->count(3)->create();

        Livewire::test(ListFaqs::class)
            ->assertCanSeeTableRecords($records)
            ->searchTable($records->first()->question)
            ->assertCanSeeTableRecords($records->take(1))
            ->assertCanNotSeeTableRecords($records->skip(1));
    }

    public function test_can_create_faq(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'question' => '¿Cómo riego mis plantas?',
            'answer' => 'Depende de la especie y la luz disponible.',
            'category' => 'Compras',
        ];

        Livewire::test(CreateFaq::class)
            ->fillForm($payload)
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(Faq::class, [
            'question' => '¿Cómo riego mis plantas?',
            'category' => 'Compras',
        ]);
    }
}
