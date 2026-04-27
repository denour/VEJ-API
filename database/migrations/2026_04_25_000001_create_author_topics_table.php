<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_topics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('author_id');
            $table->foreign('author_id')->references('id')->on('authors')->cascadeOnDelete();
            $table->string('topic');
            $table->string('category')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignUlid('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamps();

            $table->index(['author_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_topics');
    }
};
