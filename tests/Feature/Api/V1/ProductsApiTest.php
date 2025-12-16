<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_products_structure(): void
    {
        Product::factory()->count(4)->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonStructure([
                'data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_index_can_filter_by_search_type_and_stock_flags(): void
    {
        Product::factory()->create(['name' => 'Aloe Vera', 'type' => 'sale', 'in_stock' => true]);
        Product::factory()->create(['name' => 'Otro', 'type' => 'free', 'in_stock' => false]);

        $this->getJson('/api/v1/products?search=Aloe&type=sale&in_stock=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Aloe Vera', 'type' => 'sale', 'inStock' => true]);
    }

    public function test_index_can_filter_by_price_range(): void
    {
        Product::factory()->create(['name' => 'Cara', 'type' => 'sale', 'price' => 1200]);
        Product::factory()->create(['name' => 'Barata', 'type' => 'sale', 'price' => 100]);

        $this->getJson('/api/v1/products?min_price=500&max_price=2000')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Cara'])
            ->assertJsonMissing(['name' => 'Barata']);
    }

    public function test_show_returns_a_single_product(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/api/v1/products/{$product->getKey()}")
            ->assertOk()
            ->assertJsonFragment(['id' => (string) $product->getKey(), 'name' => $product->name]);
    }
}
