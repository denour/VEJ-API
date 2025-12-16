<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductsFilamentTest extends TestCase
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

        $records = Product::factory()->count(3)->create();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords($records)
            ->searchTable($records->first()->name)
            ->assertCanSeeTableRecords($records->take(1))
            ->assertCanNotSeeTableRecords($records->skip(1));
    }

    public function test_can_create_product_minimal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'name' => 'Helecho Boston',
            'type' => 'sale',
            'rating' => 0,
            'reviews' => 0,
        ];

        Livewire::test(CreateProduct::class)
            ->fillForm($payload)
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(Product::class, [
            'name' => 'Helecho Boston',
            'type' => 'sale',
        ]);
    }
}
