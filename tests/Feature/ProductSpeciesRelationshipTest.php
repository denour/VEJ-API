<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSpeciesRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_have_a_species(): void
    {
        $species = Species::create([
            'common_name' => 'Monstera',
            'scientific_name' => 'Monstera deliciosa',
        ]);

        $product = Product::create([
            'name' => 'Young Monstera',
            'type' => 'sale',
            'price' => 299.99,
            'species_id' => $species->id,
        ]);

        $this->assertNotNull($product->species);
        $this->assertEquals($species->id, $product->species->id);
        $this->assertEquals('Monstera', $product->species->common_name);
    }

    public function test_product_can_exist_without_species(): void
    {
        $product = Product::create([
            'name' => 'Mystery Plant',
            'type' => 'sale',
            'price' => 99.99,
        ]);

        $this->assertNull($product->species_id);
        $this->assertNull($product->species);
    }

    public function test_accessing_species_relationship_returns_species_model(): void
    {
        $species = Species::create([
            'common_name' => 'Pothos',
            'scientific_name' => 'Epipremnum aureum',
        ]);

        $product = Product::create([
            'name' => 'Golden Pothos',
            'type' => 'trade',
            'species_id' => $species->id,
        ]);

        $this->assertInstanceOf(Species::class, $product->species);
    }
}
