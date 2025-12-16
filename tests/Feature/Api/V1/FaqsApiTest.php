<?php

namespace Tests\Feature\Api\V1;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_faqs_structure(): void
    {
        Faq::factory()->count(5)->create();

        $this->getJson('/api/v1/faqs')
            ->assertOk()
            ->assertJsonStructure([
                'data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_index_can_filter_by_category_and_search(): void
    {
        Faq::factory()->create(['category' => 'Compras', 'question' => '¿Cómo pago?']);
        Faq::factory()->create(['category' => 'Envíos', 'question' => 'Otros temas']);

        $this->getJson('/api/v1/faqs?category=Compras&search=pago')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['category' => 'Compras']);
    }

    public function test_show_returns_a_single_faq(): void
    {
        $faq = Faq::factory()->create();

        $this->getJson("/api/v1/faqs/{$faq->getKey()}")
            ->assertOk()
            ->assertJsonFragment(['id' => (string) $faq->getKey(), 'question' => $faq->question]);
    }
}
