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
        Schema::create('astrologer_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('astrologer_id')
                ->constrained('ai_astrologers')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');

            $table->text('review')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            /*
             * One user can have only one review for one astrologer.
             */
            $table->unique(
                ['user_id', 'astrologer_id'],
                'user_astrologer_unique_review'
            );

            /*
             * Useful for astrologer review listing/statistics.
             */
            $table->index(
                ['astrologer_id', 'is_active'],
                'astrologer_reviews_active_index'
            );

            $table->index(
                ['user_id', 'is_active'],
                'user_reviews_active_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('astrologer_reviews');
    }
};