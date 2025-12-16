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
        Schema::create('image_generation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index(); // ID de la API externa
            $table->string('token')->unique(); // Token único para el callback
            $table->text('prompt');
            $table->string('size')->default('1024x1024');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('image_path')->nullable(); // Ruta local después de guardar
            $table->string('image_url')->nullable(); // URL pública
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Datos adicionales
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_generation_requests');
    }
};
