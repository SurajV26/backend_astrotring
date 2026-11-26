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
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_category_id');
            $table->string('name');
            $table->string('slug')->unique(); 
            $table->string('image')->nullable(); 
            $table->date('date')->nullable();
            $table->longText('description')->nullable();
            $table->timestamps();

            $table->foreign('policy_category_id')
                ->references('id')->on('policy_categories')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
