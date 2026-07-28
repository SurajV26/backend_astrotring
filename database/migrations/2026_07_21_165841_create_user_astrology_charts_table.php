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

            $table->string('place')->nullable();
            $table->string('state')->nullable();

            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->decimal('timezone', 4, 2)->nullable();

            $table->date('report_date')->nullable();

            $table->string('day')->nullable();

            $table->string('calculation_type')->nullable();

            $table->string('lunar_year_month')->nullable();

            $table->string('solar_month')->nullable();

            $table->integer('kali_year')->nullable();

            $table->integer('vikrama_year')->nullable();

            $table->integer('saka_year')->nullable();

            $table->time('sun_rise')->nullable();

            $table->time('sun_set')->nullable();

            $table->time('moon_rise')->nullable();

            $table->time('moon_set')->nullable();

            $table->text('tithi')->nullable();

            $table->string('moon_sign')->nullable();

            $table->text('nakshatra')->nullable();

            $table->text('rahu_kaal')->nullable();

            $table->text('gulikai')->nullable();

            $table->text('yamagandam')->nullable();

            $table->text('yoga')->nullable();

            $table->text('karana')->nullable();

            $table->text('abhijit')->nullable();

            $table->text('dhur_muhurtham')->nullable();

            $table->string('ascendant')->nullable();

            $table->string('sun_sign')->nullable();

            $table->string('moon_rashi')->nullable();

            $table->string('nakshatra_name')->nullable();

            $table->string('nakshatra_pada')->nullable();

            $table->string('nakshatra_lord')->nullable();

            $table->json('doshas')->nullable();

            $table->json('yogas')->nullable();

            $table->json('chara_karakas')->nullable();

            $table->json('planet_strength')->nullable();

            $table->json('shadbala')->nullable();

            $table->json('bhava_bala')->nullable();

            $table->json('d1_chart')->nullable();

            $table->json('d2_chart')->nullable();

            $table->json('d7_chart')->nullable();

            $table->json('d9_chart')->nullable();

            $table->json('d10_chart')->nullable();

            $table->json('d12_chart')->nullable();

            $table->json('d20_chart')->nullable();

            $table->json('d24_chart')->nullable();

            $table->json('d60_chart')->nullable();

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