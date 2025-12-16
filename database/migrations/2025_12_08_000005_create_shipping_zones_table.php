<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('zone');
            $table->string('time');
            $table->decimal('cost', 10, 2);
            $table->decimal('regular_cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('MXN');
            $table->timestamps();
            $table->index(['zone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
