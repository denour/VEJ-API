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
        Schema::table('authors', function (Blueprint $table) {
            // Change id to ULID if it's not already
            if (Schema::getColumnType('authors', 'id') !== 'string') {
                // Can't change from int to ulid easily, so we'll keep the existing id
                // and just add the new fields
            }

            // Add slug if it doesn't exist
            if (! Schema::hasColumn('authors', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }

            // Add is_active if it doesn't exist
            if (! Schema::hasColumn('authors', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('slug');
            }

            // Rename image to avatar_url if image exists
            if (Schema::hasColumn('authors', 'image') && ! Schema::hasColumn('authors', 'avatar_url')) {
                $table->renameColumn('image', 'avatar_url');
            } elseif (! Schema::hasColumn('authors', 'avatar_url')) {
                $table->string('avatar_url')->nullable()->after('is_active');
            }

            // Add persona fields
            if (! Schema::hasColumn('authors', 'background_story')) {
                $table->text('background_story')->nullable()->after('avatar_url');
            }

            if (! Schema::hasColumn('authors', 'personality_traits')) {
                $table->json('personality_traits')->nullable()->after('background_story');
            }

            if (! Schema::hasColumn('authors', 'expertise_areas')) {
                $table->json('expertise_areas')->nullable()->after('personality_traits');
            }

            // Voice Configuration
            if (! Schema::hasColumn('authors', 'sentence_style')) {
                $table->string('sentence_style')->default('varied')->after('expertise_areas');
            }

            if (! Schema::hasColumn('authors', 'vocabulary_level')) {
                $table->string('vocabulary_level')->default('conversational')->after('sentence_style');
            }

            if (! Schema::hasColumn('authors', 'tone')) {
                $table->string('tone')->default('warm')->after('vocabulary_level');
            }

            if (! Schema::hasColumn('authors', 'formality')) {
                $table->string('formality')->default('balanced')->after('tone');
            }

            // Signature Elements
            if (! Schema::hasColumn('authors', 'catchphrases')) {
                $table->json('catchphrases')->nullable()->after('formality');
            }

            if (! Schema::hasColumn('authors', 'quirks')) {
                $table->json('quirks')->nullable()->after('catchphrases');
            }

            if (! Schema::hasColumn('authors', 'recurring_topics')) {
                $table->json('recurring_topics')->nullable()->after('quirks');
            }

            if (! Schema::hasColumn('authors', 'avoided_elements')) {
                $table->json('avoided_elements')->nullable()->after('recurring_topics');
            }

            // Generated Content
            if (! Schema::hasColumn('authors', 'voice_bible')) {
                $table->text('voice_bible')->nullable()->after('avoided_elements');
            }

            if (! Schema::hasColumn('authors', 'sample_paragraph')) {
                $table->text('sample_paragraph')->nullable()->after('voice_bible');
            }

            // Stats
            if (! Schema::hasColumn('authors', 'generation_stats')) {
                $table->json('generation_stats')->nullable()->after('sample_paragraph');
            }

            // Drop old description columns if they exist
            if (Schema::hasColumn('authors', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('authors', 'detailed_description')) {
                $table->dropColumn('detailed_description');
            }
        });

        // Generate slugs for existing authors
        DB::table('authors')->whereNull('slug')->orWhere('slug', '')->get()->each(function ($author) {
            DB::table('authors')
                ->where('id', $author->id)
                ->update(['slug' => \Illuminate\Support\Str::slug($author->name)]);
        });

        // Make slug unique and not null after populating it
        // Try to add unique constraint - if it already exists, the exception will be caught
        try {
            Schema::table('authors', function (Blueprint $table) {
                $table->string('slug')->unique()->nullable(false)->change();
            });
        } catch (\Exception $e) {
            // Index already exists, which is fine
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'is_active',
                'background_story',
                'personality_traits',
                'expertise_areas',
                'sentence_style',
                'vocabulary_level',
                'tone',
                'formality',
                'catchphrases',
                'quirks',
                'recurring_topics',
                'avoided_elements',
                'voice_bible',
                'sample_paragraph',
                'generation_stats',
            ]);

            if (Schema::hasColumn('authors', 'avatar_url')) {
                $table->renameColumn('avatar_url', 'image');
            }

            $table->text('description')->nullable();
        });
    }
};
