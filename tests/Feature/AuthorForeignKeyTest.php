<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\AuthorTopic;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This test requires PostgreSQL.');
        }
    }

    public function test_posts_author_id_foreign_constraint_references_authors(): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS found FROM pg_catalog.pg_constraint c
             JOIN pg_catalog.pg_class referenced ON c.confrelid = referenced.oid
             WHERE c.conname = ?
               AND c.conrelid = ?::regclass
               AND referenced.relname = ?',
            ['posts_author_id_foreign', 'posts', 'authors']
        );

        $this->assertNotNull(
            $exists,
            'Foreign key posts_author_id_foreign referencing authors not found'
        );
    }

    public function test_author_topics_author_id_foreign_constraint_references_authors(): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS found FROM pg_catalog.pg_constraint c
             JOIN pg_catalog.pg_class referenced ON c.confrelid = referenced.oid
             WHERE c.conname = ?
               AND c.conrelid = ?::regclass
               AND referenced.relname = ?',
            ['author_topics_author_id_foreign', 'author_topics', 'authors']
        );

        $this->assertNotNull(
            $exists,
            'Foreign key author_topics_author_id_foreign referencing authors not found'
        );
    }

    public function test_post_belongs_to_author_via_is_check(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($post->author->is($author));
    }

    public function test_author_topic_belongs_to_author_via_is_check(): void
    {
        $author = Author::factory()->create();
        $topic = AuthorTopic::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($topic->author->is($author));
    }

    public function test_author_can_have_multiple_posts(): void
    {
        $author = Author::factory()->create();
        Post::factory()->count(3)->create(['author_id' => $author->id]);

        $this->assertCount(3, $author->posts);
    }

    public function test_author_can_have_multiple_topics(): void
    {
        $author = Author::factory()->create();
        AuthorTopic::factory()->count(2)->create(['author_id' => $author->id]);

        $this->assertCount(2, $author->topics);
    }
}
