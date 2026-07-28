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
        Schema::table('users', function (Blueprint $table) {

            /* BASIC */
            $table->enum('type', ['admin', 'astro', 'user', 'employee'])->after('id');
            $table->unsignedBigInteger('role_id')->after('type');
            $table->json('permissions')->nullable()->after('role_id');
            $table->string('username')->unique()->after('role_id');
            $table->string('code')->unique()->nullable()->after('username');

            $table->string('hash_token')->nullable()->after('remember_token');
            $table->string('otp', 10)->nullable()->after('hash_token');
            $table->timestamp('otp_created_at')->nullable()->after('otp');
            $table->timestamp('last_otp_sent_at')->nullable()->after('otp_created_at');
            $table->integer('otp_attempts')->default(0)->after('last_otp_sent_at');
            $table->boolean('is_two_factor_auth_enabled')->default(false)->after('otp_attempts');
            $table->string('device_token')->nullable()->after('is_two_factor_auth_enabled');

            $table->tinyInteger('status')->default(1)->after('device_token');
            $table->tinyInteger('terms_accepted')->default(0)->after('status');

            /* PERSONAL */
            $table->string('country_code', 5)->default('+91')->after('email');
            $table->string('mobile')->nullable()->after('country_code');
            $table->text('address')->nullable()->after('mobile');
            $table->string('pincode', 10)->nullable()->after('address');

            $table->date('dob')->nullable()->after('pincode');
            $table->time('birth_time')->nullable()->after('dob');
            $table->json('birth_place')->nullable()->after('birth_time');

            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('birth_place');
            $table->string('marital_status')->nullable()->after('gender');
            $table->string('occupation')->nullable()->after('marital_status');
            $table->string('profile_image')->nullable()->after('gender');
            $table->text('about')->nullable()->after('profile_image');

            /* ASTROLOGER FIELDS */
            $table->integer('experience')->nullable()->after('about');
            $table->json('expertise')->nullable()->after('experience');
            $table->json('astro_education')->nullable()->after('expertise');
            $table->json('category')->nullable()->after('astro_education');
            $table->json('languages')->nullable()->after('category');
            $table->tinyInteger('daily_available_hours')->nullable()->after('languages');
            $table->boolean('is_family_astrologer')->default(false)->after('daily_available_hours');
            $table->text('family_astrology_details')->nullable()->after('is_family_astrologer');

            $table->string('id_proof')->nullable()->after('family_astrology_details');
            $table->string('certificate')->nullable()->after('id_proof');

            /* PRICING */
            $table->decimal('chat_price', 10, 2)->default(0)->after('certificate');
            $table->decimal('call_price', 10, 2)->default(0)->after('chat_price');

            /* STATUS */
            $table->boolean('is_online')->default(false)->after('call_price');
            $table->boolean('is_busy')->default(false)->after('is_online');
            $table->timestamp('last_seen_at')->nullable()->after('is_busy');

            /* LOCATION */
            $table->unsignedBigInteger('country_id')->nullable()->after('last_seen_at');
            $table->unsignedBigInteger('state_id')->nullable()->after('country_id');
            $table->unsignedBigInteger('city_id')->nullable()->after('state_id');
            $table->unsignedBigInteger('pincode_id')->nullable()->after('city_id');

            /* HR */
            $table->decimal('salary', 10, 2)->nullable()->after('pincode_id');
            $table->date('date_of_joining')->nullable()->after('salary');

            /* AUDIT */
            $table->unsignedBigInteger('created_by')->nullable()->after('date_of_joining');
            $table->unsignedBigInteger('modified_by')->nullable()->after('created_by');

            $table->softDeletes();

            /* FOREIGN KEYS */
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('modified_by')->references('id')->on('users')->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};