<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_generation_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('image_generation_requests', 'post_id')) {
                $table->foreignUlid('post_id')->nullable()->after('external_id')->constrained('posts')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('image_generation_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('image_generation_requests', 'post_id')) {
                $table->dropConstrainedForeignId('post_id');
            }
        });
    }
};
