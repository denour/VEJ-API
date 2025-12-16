<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Basic info
            $table->string('image')->nullable();
            $table->json('images')->nullable();
            $table->string('name');
            $table->string('scientific_name')->nullable();

            // Botanical metadata
            $table->string('care_level')->nullable();
            $table->string('sunlight')->nullable();
            $table->string('watering')->nullable();

            // Product condition
            $table->string('condition')->nullable();
            $table->string('size')->nullable();
            $table->boolean('is_rare')->default(false);

            // Transaction details
            $table->string('type'); // sale | trade | free
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('MXN');

            // Rating
            $table->decimal('rating', 4, 2)->default(0);
            $table->unsignedInteger('reviews')->default(0);

            // Availability
            $table->boolean('in_stock')->default(true);
            $table->unsignedInteger('quantity')->nullable();

            $table->timestamps();

            $table->index(['name']);
            $table->index(['type']);
            $table->index(['in_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
