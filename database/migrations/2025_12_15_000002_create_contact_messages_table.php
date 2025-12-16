<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->timestamps();
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
