<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostAuthorRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_requires_an_author(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Post::create([
            'title' => 'Test Post',
            'content' => [['type' => 'paragraph', 'content' => 'Test content']],
            'category' => 'care',
            'status' => 'draft',
        ]);
    }

    public function test_post_can_have_an_author(): void
    {
        $author = Author::create([
            'name' => 'Jane Doe',
            'image' => 'authors/jane.jpg',
            'description' => 'Expert writer',
        ]);

        $post = Post::create([
            'title' => 'How to Care for Plants',
            'content' => [['type' => 'paragraph', 'content' => 'Care guide']],
            'category' => 'care',
            'status' => 'draft',
            'author_id' => $author->id,
        ]);

        $this->assertNotNull($post->author);
        $this->assertEquals($author->id, $post->author->id);
        $this->assertEquals('Jane Doe', $post->author->name);
    }

    public function test_accessing_author_relationship_returns_author_model(): void
    {
        $author = Author::create([
            'name' => 'John Smith',
        ]);

        $post = Post::create([
            'title' => 'Plant Guide',
            'content' => [['type' => 'paragraph', 'content' => 'Guide']],
            'category' => 'guides',
            'status' => 'published',
            'author_id' => $author->id,
        ]);

        $this->assertInstanceOf(Author::class, $post->author);
    }

    public function test_author_can_have_multiple_posts(): void
    {
        $author = Author::create([
            'name' => 'Alice Writer',
        ]);

        $post1 = Post::create([
            'title' => 'First Post',
            'content' => [['type' => 'paragraph', 'content' => 'Content 1']],
            'category' => 'care',
            'status' => 'published',
            'author_id' => $author->id,
        ]);

        $post2 = Post::create([
            'title' => 'Second Post',
            'content' => [['type' => 'paragraph', 'content' => 'Content 2']],
            'category' => 'guides',
            'status' => 'published',
            'author_id' => $author->id,
        ]);

        $this->assertCount(2, $author->posts);
        $this->assertTrue($author->posts->contains($post1));
        $this->assertTrue($author->posts->contains($post2));
    }
}
