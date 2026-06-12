<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_settings_returns_key_value_collection(): void
    {
        Setting::query()->create(['key' => 'site_name', 'value' => ['value' => 'Vida en el Jardín'], 'type' => 'string']);
        Setting::query()->create(['key' => 'maintenance', 'value' => ['value' => true], 'type' => 'boolean']);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['key', 'value', 'type', 'updated_at']]])
            ->assertJsonFragment(['key' => 'site_name', 'value' => 'Vida en el Jardín'])
            ->assertJsonFragment(['key' => 'maintenance', 'value' => true]);
    }

    public function test_get_settings_returns_empty_collection_when_no_settings(): void
    {
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
