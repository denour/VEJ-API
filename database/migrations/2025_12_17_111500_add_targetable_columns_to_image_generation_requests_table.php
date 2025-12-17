<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_generation_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('image_generation_requests', 'targetable_type')) {
                $table->string('targetable_type')->nullable()->after('post_id');
            }
            if (! Schema::hasColumn('image_generation_requests', 'targetable_id')) {
                $table->ulid('targetable_id')->nullable()->after('targetable_type');
            }

            $table->index(['targetable_type', 'targetable_id'], 'igr_targetable_index');
        });
    }

    public function down(): void
    {
        Schema::table('image_generation_requests', function (Blueprint $table): void {
            if (Schema::hasIndex('image_generation_requests', 'igr_targetable_index')) {
                $table->dropIndex('igr_targetable_index');
            }
            if (Schema::hasColumn('image_generation_requests', 'targetable_id')) {
                $table->dropColumn('targetable_id');
            }
            if (Schema::hasColumn('image_generation_requests', 'targetable_type')) {
                $table->dropColumn('targetable_type');
            }
        });
    }
};
