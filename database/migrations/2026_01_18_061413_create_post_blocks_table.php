<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('post_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // paragraph, heading, image, list, quote, code, video
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->json('data')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index('post_id');
            $table->index(['post_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_blocks');
    }
};
