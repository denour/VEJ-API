<?php

namespace Tests\Feature\Api\V1;

use App\Models\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeciesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_species_structure(): void
    {
        Species::factory()->count(4)->create();

        $this->getJson('/api/v1/species')
            ->assertOk()
            ->assertJsonStructure([
                'data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_index_can_filter_by_search_and_traits(): void
    {
        Species::factory()->create(['common_name' => 'Lengua de Suegra', 'sunlight' => 'high']);
        Species::factory()->create(['common_name' => 'Helecho', 'sunlight' => 'low']);

        $this->getJson('/api/v1/species?search=Lengua&sunlight=high')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['commonName' => 'Lengua de Suegra', 'sunlight' => 'high']);
    }

    public function test_show_returns_a_single_species(): void
    {
        $species = Species::factory()->create();

        $this->getJson("/api/v1/species/{$species->getKey()}")
            ->assertOk()
            ->assertJsonFragment(['id' => (string) $species->getKey(), 'commonName' => $species->common_name]);
    }
}
