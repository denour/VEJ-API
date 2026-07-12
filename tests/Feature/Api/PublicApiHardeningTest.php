<?php

namespace Tests\Feature\Api;

use App\Models\NewsletterSubscription;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The throttle middleware stores hit counters in the cache, which
        // persists across tests in one process; clear it so each test starts
        // with a fresh rate-limit budget.
        Cache::flush();
    }

    public function test_posts_index_caps_per_page_at_fifty(): void
    {
        Post::factory()->count(60)->create(['status' => 'published']);

        $response = $this->getJson('/api/v1/posts?per_page=1000')
            ->assertOk();

        $this->assertLessThanOrEqual(50, count($response->json('data')));
        $this->assertSame(50, $response->json('meta.per_page'));
    }

    public function test_newsletter_subscribe_is_idempotent_and_hides_membership(): void
    {
        $first = $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'a@example.com'])
            ->assertStatus(201);

        // Re-subscribing the same email returns the SAME success response, not a
        // 422 that would leak that the address is already subscribed.
        $second = $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'a@example.com'])
            ->assertStatus(201);

        $this->assertSame($first->json('message'), $second->json('message'));
        $this->assertSame(1, NewsletterSubscription::query()->where('email', 'a@example.com')->count());
    }

    public function test_public_write_endpoints_are_rate_limited(): void
    {
        // The write group is throttled at 10/min; the 11th request is rejected.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/newsletter/subscribe', ['email' => "user{$i}@example.com"])
                ->assertStatus(201);
        }

        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'overflow@example.com'])
            ->assertStatus(429);
    }
}
