<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\SpeciesResource\Pages\CreateSpecies;
use App\Filament\Resources\SpeciesResource\Pages\ListSpecies;
use App\Models\Species;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpeciesFilamentTest extends TestCase
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

        $records = Species::factory()->count(3)->create();

        Livewire::test(ListSpecies::class)
            ->assertCanSeeTableRecords($records)
            ->searchTable($records->first()->common_name)
            ->assertCanSeeTableRecords($records->take(1))
            ->assertCanNotSeeTableRecords($records->skip(1));
    }

    public function test_can_create_species_minimal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'common_name' => 'Monstera deliciosa',
            'scientific_name' => 'Monstera deliciosa',
        ];

        Livewire::test(CreateSpecies::class)
            ->fillForm($payload)
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(Species::class, [
            'common_name' => 'Monstera deliciosa',
            'scientific_name' => 'Monstera deliciosa',
        ]);
    }
}
