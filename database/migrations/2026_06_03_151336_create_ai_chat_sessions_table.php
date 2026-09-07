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
        Schema::create('ai_chat_sessions', function (Blueprint $table) {

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('astrologer_id');
            $table->unsignedBigInteger('expertise_id');
            $table->timestamp('chat_active_since')->nullable();
            $table->timestamp('chat_last_seen_at')->nullable();
            $table->unsignedInteger('chat_billed_minutes')->default(0);
            $table->boolean('chat_free_used')->default(false);
            $table->integer('free_messages_used')->default(0);
            $table->integer('paid_messages')->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->timestamps();

            $table->index('user_id');
            $table->index('astrologer_id');
            $table->index('expertise_id');
            $table->index('status');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('astrologer_id')
                ->references('id')
                ->on('ai_astrologers')
                ->cascadeOnDelete();

            $table->foreign('expertise_id')
                ->references('id')
                ->on('ai_astrologer_expertises')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chat_sessions');
    }
};
