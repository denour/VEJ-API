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
        // This migration fixes the slug column in production (PostgreSQL) where it failed
        // Check if slug column exists
        if (Schema::hasColumn('authors', 'slug')) {
            // Column exists, just make sure it has data
            DB::table('authors')->whereNull('slug')->orWhere('slug', '')->get()->each(function ($author) {
                DB::table('authors')
                    ->where('id', $author->id)
                    ->update(['slug' => \Illuminate\Support\Str::slug($author->name)]);
            });

            // Try to add unique constraint if it doesn't exist
            // Wrap in try-catch because if it already exists, it will throw an error
            try {
                Schema::table('authors', function (Blueprint $table) {
                    $table->string('slug')->unique()->nullable(false)->change();
                });
            } catch (\Exception $e) {
                // Index already exists or column already NOT NULL, which is fine
            }
        } else {
            // Column doesn't exist, add it as nullable first
            Schema::table('authors', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
            });

            // Generate slugs
            DB::table('authors')->get()->each(function ($author) {
                DB::table('authors')
                    ->where('id', $author->id)
                    ->update(['slug' => \Illuminate\Support\Str::slug($author->name)]);
            });

            // Make it unique and not null
            Schema::table('authors', function (Blueprint $table) {
                $table->string('slug')->unique()->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
