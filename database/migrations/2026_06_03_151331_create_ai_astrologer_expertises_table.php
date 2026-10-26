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
        Schema::create('ai_astrologer_expertises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ai_astrologer_id')
                ->constrained('ai_astrologers')
                ->cascadeOnDelete();

            $table->string('name');

            $table->string('slug')->unique();

            $table->json('relevant_chart')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_astrologer_expertises');
    }
};
