<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserAstrologyChart extends Model
{
    use HasFactory;

    protected $fillable = [

        'user_id',

        'place',
        'state',
        'latitude',
        'longitude',
        'timezone',

        'report_date',
        'day',
        'calculation_type',
        'lunar_year_month',
        'solar_month',
        'kali_year',
        'vikrama_year',
        'saka_year',
        'sun_rise',
        'sun_set',
        'moon_rise',
        'moon_set',
        'tithi',
        'moon_sign',
        'nakshatra',
        'rahu_kaal',
        'gulikai',
        'yamagandam',
        'yoga',
        'karana',
        'abhijit',
        'dhur_muhurtham',

        'ascendant',
        'sun_sign',
        'moon_rashi',
        'nakshatra_name',
        'nakshatra_pada',
        'nakshatra_lord',

        'doshas',
        'yogas',
        'chara_karakas',
        'planet_strength',
        'shadbala',
        'bhava_bala',

        'd1_chart',
        'd2_chart',
        'd7_chart',
        'd9_chart',
        'd10_chart',
        'd12_chart',
        'd20_chart',
        'd24_chart',
        'd60_chart',

        'raw_data',
    ];

    protected $casts = [

        'report_date' => 'date',

        'doshas' => 'array',
        'yogas' => 'array',
        'chara_karakas' => 'array',
        'planet_strength' => 'array',
        'shadbala' => 'array',
        'bhava_bala' => 'array',

        'd1_chart' => 'array',
        'd2_chart' => 'array',
        'd7_chart' => 'array',
        'd9_chart' => 'array',
        'd10_chart' => 'array',
        'd12_chart' => 'array',
        'd20_chart' => 'array',
        'd24_chart' => 'array',
        'd60_chart' => 'array',

        'raw_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}