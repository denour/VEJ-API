<?php

namespace Tests\Feature\Filament\Species;

use App\Filament\Resources\SpeciesResource\Pages\CreateSpecies;
use App\Models\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateSpeciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_species_uses_provided_names(): void
    {
        $payload = [
            'common_name' => 'Higuera Lyrata',
            'scientific_name' => 'Ficus lyrata',
            'family' => 'Moraceae',
            'origin' => 'África Occidental',
            'description' => 'Planta ornamental popular en interiores.',
        ];

        Livewire::test(CreateSpecies::class)
            ->fillForm($payload)
            ->call('create');

        $this->assertDatabaseHas(Species::class, [
            'common_name' => 'Higuera Lyrata',
            'scientific_name' => 'Ficus lyrata',
        ]);
    }
}
