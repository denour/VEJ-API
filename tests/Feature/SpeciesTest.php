<?php

namespace Tests\Feature;

use App\Models\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_species(): void
    {
        $species = Species::create([
            'common_name' => 'Monstera',
            'scientific_name' => 'Monstera deliciosa',
            'family' => 'Araceae',
            'origin' => 'Central America',
            'description' => 'A popular tropical plant',
            'care_level' => 'easy',
            'sunlight' => 'medium',
            'watering' => 'medium',
            'toxicity' => 'pets',
            'growth_rate' => 'fast',
            'max_height_cm' => 300,
        ]);

        $this->assertDatabaseHas('species', [
            'common_name' => 'Monstera',
            'scientific_name' => 'Monstera deliciosa',
        ]);

        $this->assertNotNull($species->id);
        $this->assertEquals('Monstera', $species->common_name);
    }

    public function test_species_requires_common_name_and_scientific_name(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Species::create([
            'family' => 'Araceae',
        ]);
    }

    public function test_species_can_have_nullable_fields(): void
    {
        $species = Species::create([
            'common_name' => 'Test Plant',
            'scientific_name' => 'Testus plantus',
        ]);

        $this->assertNull($species->family);
        $this->assertNull($species->origin);
        $this->assertNull($species->description);
        $this->assertNull($species->care_level);
    }
}
