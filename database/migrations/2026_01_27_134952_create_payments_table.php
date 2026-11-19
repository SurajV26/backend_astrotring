<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->enum('platform', ['astrotring', 'astrotring_store', 'usastro_subscription'])->default('usastro_subscription');
            $table->string('order_id')->nullable()->index();
            // $table->string('payment_gateway')->default('razorpay');
            $table->enum('payment_gateway', ['razorpay', 'sbi']);
            $table->string('transaction_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('INR');
            $table->enum('payment_status', ['pending','success', 'refunded', 'failed'])->default('pending');
            $table->string('payment_mode')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->json('payment_request_data')->nullable();
            $table->json('payment_response_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};