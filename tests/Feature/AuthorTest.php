<?php

namespace Tests\Feature;

use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_requires_name(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Author::create([
            'background_story' => 'Test description',
        ]);
    }
}
