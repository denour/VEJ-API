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

    public function test_show_returns_a_single_species_by_slug(): void
    {
        $species = Species::factory()->create();

        $this->getJson("/api/v1/species/{$species->slug}")
            ->assertOk()
            ->assertJsonFragment(['id' => (string) $species->getKey(), 'slug' => $species->slug, 'commonName' => $species->common_name]);
    }

    public function test_slug_is_generated_from_common_name_on_create(): void
    {
        $species = Species::factory()->create(['common_name' => 'Monstera Deliciosa']);

        $this->assertSame('monstera-deliciosa', $species->slug);
    }

    public function test_duplicate_common_names_get_unique_slugs(): void
    {
        $first = Species::factory()->create(['common_name' => 'Pothos']);
        $second = Species::factory()->create(['common_name' => 'Pothos']);

        $this->assertSame('pothos', $first->slug);
        $this->assertSame('pothos-2', $second->slug);
    }

    public function test_index_exposes_slug(): void
    {
        Species::factory()->create(['common_name' => 'Helecho']);

        $this->getJson('/api/v1/species')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'helecho']);
    }
}
