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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('postcode', 16)->index();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('delivery_radius_km', 6, 2)->default(5.00);

            // Bonus checkbox: operating hours (simple)
            $table->string('timezone')->default('Europe/London');
            $table->time('opens_at')->nullable();   // null => always open
            $table->time('closes_at')->nullable();

            $table->timestamps();

            $table->index(['lat', 'lng']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
