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
        Schema::create('authors', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Basic Information
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('avatar_url')->nullable();

            // Background & Identity
            $table->text('background_story')->nullable();
            $table->json('personality_traits')->nullable();
            $table->json('expertise_areas')->nullable();

            // Voice Configuration
            $table->string('sentence_style')->default('varied');
            $table->string('vocabulary_level')->default('conversational');
            $table->string('tone')->default('warm');
            $table->string('formality')->default('balanced');

            // Signature Elements
            $table->json('catchphrases')->nullable();
            $table->json('quirks')->nullable();
            $table->json('recurring_topics')->nullable();
            $table->json('avoided_elements')->nullable();

            // Generated Content
            $table->text('voice_bible')->nullable();
            $table->text('sample_paragraph')->nullable();

            // Stats
            $table->json('generation_stats')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
