<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_policies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->json('guarantee_reasons')->nullable();
            $table->json('return_steps')->nullable();
            $table->json('conditions')->nullable();
            $table->json('quick_cards')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_policies');
    }
};
