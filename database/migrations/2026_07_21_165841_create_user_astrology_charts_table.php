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
        Schema::create('user_astrology_charts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // Basic Astrology
            $table->string('lagna')->nullable();
            $table->string('moon_sign')->nullable();
            $table->string('sun_sign')->nullable();
            $table->string('nakshatra')->nullable();
            $table->string('pada')->nullable();

            // Planet Positions
            $table->json('planets')->nullable();

            // Complete Divisional Charts
            $table->json('charts')->nullable();

            // Future Prediction Data
            $table->json('dasha')->nullable();
            $table->json('transit')->nullable();

            // Raw Response (if generated from any engine later)
            $table->longText('raw_data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_astrology_charts');
    }
};