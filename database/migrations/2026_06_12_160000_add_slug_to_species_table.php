<?php

use App\Models\Species;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('species', 'slug')) {
            Schema::table('species', function (Blueprint $table): void {
                $table->string('slug')->nullable()->unique()->after('scientific_name');
            });
        }

        // Backfill slugs from common_name, ensuring uniqueness
        Species::query()->whereNull('slug')->orderBy('created_at')->get()->each(function (Species $species): void {
            $base = Str::slug($species->common_name);
            $slug = $base;
            $i = 2;
            while (Species::query()->where('slug', $slug)->where('id', '!=', $species->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $species->forceFill(['slug' => $slug])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('species', function (Blueprint $table): void {
            $table->dropColumn('slug');
        });
    }
};
