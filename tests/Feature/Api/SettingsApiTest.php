<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_settings_returns_defaults(): void
    {
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'site_name', 'phone', 'address', 'socials', 'logo', 'favicon', 'updated_at',
                ],
            ]);
    }

    public function test_put_settings_requires_auth(): void
    {
        $this->putJson('/api/v1/settings', ['site_name' => 'Mi Sitio'])
            ->assertStatus(401);
    }

    public function test_put_settings_updates_values_when_authenticated(): void
    {
        $user = User::factory()->create();

        $payload = [
            'site_name' => 'Mi Sitio',
            'phone' => '+52 55 1234 5678',
            'address' => 'CDMX',
            'socials' => [
                'facebook' => 'https://facebook.com/misitio',
                'instagram' => 'https://instagram.com/misitio',
            ],
            'logo' => 'https://cdn.example.com/logo.png',
            'favicon' => 'https://cdn.example.com/favicon.ico',
        ];

        $this->actingAs($user)
            ->putJson('/api/v1/settings', $payload)
            ->assertOk()
            ->assertJsonPath('data.site_name', 'Mi Sitio');

        $this->assertDatabaseHas(Setting::class, [
            'site_name' => 'Mi Sitio',
            'phone' => '+52 55 1234 5678',
            'address' => 'CDMX',
        ]);
    }
}
