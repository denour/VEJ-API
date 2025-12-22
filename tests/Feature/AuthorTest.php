<?php

namespace Tests\Feature;

use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_an_author(): void
    {
        $author = Author::create([
            'name' => 'Jane Doe',
            'image' => 'authors/jane-doe.jpg',
            'description' => 'Expert gardener and botanist',
        ]);

        $this->assertDatabaseHas('authors', [
            'name' => 'Jane Doe',
            'description' => 'Expert gardener and botanist',
        ]);

        $this->assertNotNull($author->id);
        $this->assertEquals('Jane Doe', $author->name);
        $this->assertEquals('authors/jane-doe.jpg', $author->image);
    }

    public function test_author_requires_name(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Author::create([
            'image' => 'authors/test.jpg',
            'description' => 'Test description',
        ]);
    }

    public function test_author_can_have_nullable_image_and_description(): void
    {
        $author = Author::create([
            'name' => 'John Smith',
        ]);

        $this->assertNull($author->image);
        $this->assertNull($author->description);
        $this->assertEquals('John Smith', $author->name);
    }

    public function test_can_create_author_with_detailed_description(): void
    {
        $detailedDescription = [
            'tone' => 'Conversacional y cercano',
            'personality' => 'Entusiasta y educativo',
            'writing_style' => 'Claro y accesible',
            'themes' => ['Plantas tropicales', 'cuidados básicos', 'jardinería urbana'],
            'editorial_focus' => 'Educación práctica',
        ];

        $author = Author::create([
            'name' => 'María García',
            'image' => 'authors/maria.jpg',
            'description' => 'Experta en jardinería',
            'detailed_description' => $detailedDescription,
        ]);

        $this->assertDatabaseHas('authors', [
            'name' => 'María García',
        ]);

        $this->assertEquals($detailedDescription, $author->detailed_description);
        $this->assertEquals('Conversacional y cercano', $author->detailed_description['tone']);
        $this->assertEquals('Entusiasta y educativo', $author->detailed_description['personality']);
    }

    public function test_author_can_have_nullable_detailed_description(): void
    {
        $author = Author::create([
            'name' => 'Pedro López',
        ]);

        $this->assertNull($author->detailed_description);
    }
}
