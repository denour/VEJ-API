<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('social_image')->nullable();
            $table->string('facebook_post_id')->nullable();
            $table->string('instagram_post_id')->nullable();
            $table->timestamp('social_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn(['social_image', 'facebook_post_id', 'instagram_post_id', 'social_published_at']);
        });
    }
};
