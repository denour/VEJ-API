<?php

namespace Tests\Feature\Api\V1;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_posts_structure(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/posts');

        $response->assertOk()
            ->assertJsonStructure([
                'data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_index_can_filter_by_category_and_search(): void
    {
        Post::factory()->create(['title' => 'Guía de riego', 'category' => 'Cuidado']);
        Post::factory()->create(['title' => 'Otra cosa', 'category' => 'Otro']);

        $this->getJson('/api/v1/posts?category=Cuidado&search=riego')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['category' => 'Cuidado', 'title' => 'Guía de riego']);
    }

    public function test_show_returns_a_single_post(): void
    {
        $post = Post::factory()->create();

        $this->getJson("/api/v1/posts/{$post->getKey()}")
            ->assertOk()
            ->assertJsonFragment(['id' => (string) $post->getKey(), 'title' => $post->title]);
    }
}
