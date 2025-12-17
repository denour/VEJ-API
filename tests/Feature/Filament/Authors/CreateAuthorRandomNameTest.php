<?php

namespace Tests\Feature\Filament\Authors;

use App\Filament\Resources\Authors\Pages\CreateAuthor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateAuthorRandomNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_random_name_action_sets_name_field(): void
    {
        Livewire::test(CreateAuthor::class)
            ->callAction('randomName')
            ->assertFormSet(['name' => fn ($value) => is_string($value) && $value !== '']);
    }
}
