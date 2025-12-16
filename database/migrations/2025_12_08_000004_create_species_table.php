<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('common_name');
            $table->string('scientific_name');
            $table->string('family')->nullable();
            $table->string('origin')->nullable();
            $table->text('description')->nullable();
            $table->string('care_level')->nullable(); // easy | medium | hard
            $table->string('sunlight')->nullable();   // low | medium | high
            $table->string('watering')->nullable();   // low | medium | high
            $table->string('image')->nullable();
            $table->json('images')->nullable();
            $table->string('toxicity')->nullable();   // none | pets | humans | both
            $table->string('growth_rate')->nullable(); // slow | medium | fast
            $table->unsignedInteger('max_height_cm')->nullable();
            $table->timestamps();

            $table->index(['common_name']);
            $table->index(['scientific_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species');
    }
};
