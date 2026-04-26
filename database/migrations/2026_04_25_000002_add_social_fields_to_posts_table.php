<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('social_image')->nullable()->after('cover_image');
            $table->string('facebook_post_id')->nullable()->after('status');
            $table->string('instagram_post_id')->nullable()->after('facebook_post_id');
            $table->timestamp('social_published_at')->nullable()->after('instagram_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn(['social_image', 'facebook_post_id', 'instagram_post_id', 'social_published_at']);
        });
    }
};
