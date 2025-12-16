<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->json('content');
            $table->string('cover_image')->nullable();
            $table->string('category')->index();
            $table->json('tags')->nullable();
            $table->json('author');
            $table->json('list')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('reading_time')->nullable();
            $table->boolean('featured')->default(false)->index();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
