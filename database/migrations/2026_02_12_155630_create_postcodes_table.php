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
       Schema::create('postcodes', function (Blueprint $table) {
            $table->id();
            $table->string('postcode', 16)->unique();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamps();

            $table->index(['lat', 'lng']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postcodes');
    }
};
