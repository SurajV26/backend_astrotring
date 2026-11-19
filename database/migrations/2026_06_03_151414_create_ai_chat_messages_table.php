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
        Schema::create('ai_chat_messages', function (Blueprint $table) {

            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('question_id')->nullable();
            $table->enum('sender', ['user', 'assistant']);
            $table->longText('message');
            $table->boolean('is_free')->default(false);
            $table->boolean('question_credit_used')->default(false);
            $table->decimal('charged_amount', 10, 2) ->default(0);
            $table->string('model')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->timestamps();

            $table->index('session_id');
            $table->index('question_id');
            $table->index('sender');

            $table->foreign('session_id')
                ->references('id')
                ->on('ai_chat_sessions')
                ->cascadeOnDelete();

            $table->foreign('question_id')
                ->references('id')
                ->on('ai_astrologer_expertise_questions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
