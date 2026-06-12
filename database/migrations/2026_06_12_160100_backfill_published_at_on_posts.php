<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Post::query()
            ->where('status', 'published')
            ->whereNull('published_at')
            ->get()
            ->each(fn (Post $post) => $post->forceFill(['published_at' => $post->created_at])->saveQuietly());
    }

    public function down(): void
    {
        // Irreversible data backfill
    }
};
