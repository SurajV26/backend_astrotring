<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAstrologyChart;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AstrologyChartService
{
    public function generate(User $user): void
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Validate Birth Details
            |--------------------------------------------------------------------------
            */

            if (
                empty($user->dob) ||
                empty($user->birth_time) ||
                empty($user->birth_place)
            ) {
                Log::warning('Birth details missing.', [
                    'user_id' => $user->id,
                ]);

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Get Birth Place JSON
            |--------------------------------------------------------------------------
            */

            $birthPlace = is_array($user->birth_place)
                ? $user->birth_place
                : json_decode($user->birth_place, true);

            if (!is_array($birthPlace)) {

                Log::error('Invalid Birth Place JSON', [
                    'user_id' => $user->id,
                    'birth_place' => $user->birth_place,
                ]);

                return;
            }

            $displayName = $birthPlace['displayName'] ?? null;
            $country     = $birthPlace['country'] ?? null;
            $place       = $birthPlace['place'] ?? null;
            $state       = $birthPlace['state'] ?? null;
            $latitude    = (float) ($birthPlace['latitude'] ?? 0);
            $longitude   = (float) ($birthPlace['longitude'] ?? 0);
            $timezone    = (float) ($birthPlace['timezone'] ?? 0);
            $elevation   = (float) ($birthPlace['elevation'] ?? 0);

            if (
                empty($latitude) ||
                empty($longitude) ||
                !isset($birthPlace['timezone'])
            ) {

                Log::error('Latitude/Longitude Missing', [
                    'user_id' => $user->id,
                    'birth_place' => $birthPlace,
                ]);

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Generate Horoscope
            |--------------------------------------------------------------------------
            */

            $horoscopeResponse = Http::timeout(120)
                ->acceptJson()
                ->post(config('services.jhora.base_url') . '/horoscope', [

                    'date' => Carbon::parse($user->dob)->format('Y-m-d'),

                    'time' => strlen($user->birth_time) == 5
                        ? $user->birth_time . ':00'
                        : $user->birth_time,

                    'latitude'  => $latitude,
                    'longitude' => $longitude,
                    'timezone'  => $timezone,
                    'place'     => $displayName ?? $place,
                ]);

            if (!$horoscopeResponse->successful()) {

                Log::error('Horoscope API Failed', [
                    'user_id' => $user->id,
                    'status' => $horoscopeResponse->status(),
                    'response' => $horoscopeResponse->body(),
                ]);

                return;
            }

            $chart = $horoscopeResponse->json();

            $horoscope = $chart['horoscope'] ?? [];

            if (empty($horoscope)) {

                Log::error('Horoscope section missing', [
                    'user_id' => $user->id,
                    'response' => $chart,
                ]);

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Extract Horoscope Data
            |--------------------------------------------------------------------------
            */

            $calendar = $horoscope['calendar_info'] ?? [];

            $divisional = $horoscope['divisional_charts'] ?? [];

            $doshas = $horoscope['doshas'] ?? [];

            $yogas = $horoscope['yogas'] ?? [];

            $karakas = $horoscope['chara_karakas'] ?? [];

            $planetStrength = $horoscope['planetary_states'] ?? [];

            $shadbala = $horoscope['shad_bala'] ?? [];

            $bhavaBala = $horoscope['bhava_bala'] ?? [];

            /*
            |--------------------------------------------------------------------------
            | D1 Chart
            |--------------------------------------------------------------------------
            */

            $d1 = $divisional['D-1_rasi'] ?? [];

            $ascendant = $d1['Ascendant']['sign'] ?? null;

            $sunSign = $d1['Sun']['sign'] ?? null;

            $moonRashi = $d1['Moon']['sign'] ?? null;

            /*
            |--------------------------------------------------------------------------
            | Nakshatra
            |--------------------------------------------------------------------------
            */

            $nakshatraData = [];

            if (isset($horoscope['nakshatra_pada']['Moon'])) {

                $nakshatraData = $horoscope['nakshatra_pada']['Moon'];

            } elseif (isset($horoscope['nakshatra_pada']['Ascendant'])) {

                $nakshatraData = $horoscope['nakshatra_pada']['Ascendant'];
            }

            $nakshatraName = $nakshatraData['nakshatra'] ?? null;

            $nakshatraPada = $nakshatraData['pada'] ?? null;

            $nakshatraLord = $nakshatraData['nakshatra_lord'] ?? null;

            /*
            |--------------------------------------------------------------------------
            | Calendar Values
            |--------------------------------------------------------------------------
            */

            $reportDate = !empty($calendar['Report Date'])
                ? Carbon::parse($calendar['Report Date'])->format('Y-m-d')
                : null;

            $sunRise = !empty($calendar['Sun Rise'])
                ? substr($calendar['Sun Rise'], 0, 8)
                : null;

            $sunSet = !empty($calendar['Sun Set'])
                ? substr($calendar['Sun Set'], 0, 8)
                : null;

            $moonRise = !empty($calendar['Moon Rise'])
                ? substr($calendar['Moon Rise'], 0, 8)
                : null;

            $moonSet = !empty($calendar['Moon Set'])
                ? substr($calendar['Moon Set'], 0, 8)
                : null;

            if (!is_array($chart) || empty($chart)) {

                Log::error('Invalid Horoscope Response', [
                    'user_id' => $user->id,
                    'response' => $chart,
                ]);

                return;
            }

            Log::info('Horoscope Response', [
                'user_id' => $user->id,
                'size' => strlen(json_encode($chart)),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Save Horoscope
            |--------------------------------------------------------------------------
            */

            UserAstrologyChart::updateOrCreate(

                [
                    'user_id' => $user->id,
                ],

                [

                    /*
                    |--------------------------------------------------------------------------
                    | Birth Location
                    |--------------------------------------------------------------------------
                    */

                    'place' => $place,
                    'state' => $state,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timezone' => $timezone,

                    /*
                    |--------------------------------------------------------------------------
                    | Calendar Info
                    |--------------------------------------------------------------------------
                    */

                    'report_date' => $reportDate,

                    'day' => $calendar['Day'] ?? null,

                    'calculation_type' => $calendar['Calcuation Type:'] ?? null,

                    'lunar_year_month' => $calendar['Lunar Year/Month:'] ?? null,

                    'solar_month' => $calendar['Solar Month:'] ?? null,

                    'kali_year' => $calendar['Kali Year'] ?? null,

                    'vikrama_year' => $calendar['Vikarama Year'] ?? null,

                    'saka_year' => $calendar['Saka Year'] ?? null,

                    'sun_rise' => $sunRise,

                    'sun_set' => $sunSet,

                    'moon_rise' => $moonRise,

                    'moon_set' => $moonSet,

                    'tithi' => $calendar['Tithi'] ?? null,

                    'moon_sign' => $calendar['Raasi'] ?? null,

                    'nakshatra' => $calendar['Nakshatram'] ?? null,

                    'rahu_kaal' => $calendar['Raagu Kaalam'] ?? null,

                    'gulikai' => $calendar['KuLigai'] ?? null,

                    'yamagandam' => $calendar['Yamagandam'] ?? null,

                    'yoga' => $calendar['Yoga'] ?? null,

                    'karana' => $calendar['Karana'] ?? null,

                    'abhijit' => $calendar['Abhijit'] ?? null,

                    'dhur_muhurtham' => $calendar['Dhur Muhurtham'] ?? null,

                    /*
                    |--------------------------------------------------------------------------
                    | Basic Astrology
                    |--------------------------------------------------------------------------
                    */

                    'ascendant' => $ascendant,

                    'sun_sign' => $sunSign,

                    'moon_rashi' => $moonRashi,

                    'nakshatra_name' => $nakshatraName,

                    'nakshatra_pada' => $nakshatraPada,

                    'nakshatra_lord' => $nakshatraLord,

                    /*
                    |--------------------------------------------------------------------------
                    | JSON Data
                    |--------------------------------------------------------------------------
                    */

                    'doshas' => $doshas,

                    'yogas' => $yogas,

                    'chara_karakas' => $karakas,

                    'planet_strength' => $planetStrength,

                    'shadbala' => $shadbala,

                    'bhava_bala' => $bhavaBala,

                    /*
                    |--------------------------------------------------------------------------
                    | Divisional Charts
                    |--------------------------------------------------------------------------
                    */

                    'd1_chart' => is_array($divisional['D-1_rasi'] ?? null) ? $divisional['D-1_rasi'] : [],

                    'd2_chart' => is_array($divisional['D-2_hora'] ?? null) ? $divisional['D-2_hora'] : [],

                    'd7_chart' => is_array($divisional['D-7_saptamsa'] ?? null) ? $divisional['D-7_saptamsa'] : [],

                    'd9_chart' => is_array($divisional['D-9_navamsa'] ?? null) ? $divisional['D-9_navamsa'] : [],

                    'd10_chart' => is_array($divisional['D-10_dasamsa'] ?? null) ? $divisional['D-10_dasamsa'] : [],

                    'd12_chart' => is_array($divisional['D-12_dwadasamsa'] ?? null) ? $divisional['D-12_dwadasamsa'] : [],

                    'd20_chart' => is_array($divisional['D-20_vimsamsa'] ?? null) ? $divisional['D-20_vimsamsa'] : [],

                    'd24_chart' => is_array($divisional['D-24_chaturvimsamsa'] ?? null) ? $divisional['D-24_chaturvimsamsa'] : [],

                    'd60_chart' => is_array($divisional['D-60_shastiamsa'] ?? null) ? $divisional['D-60_shastiamsa'] : [],

                    /*
                    |--------------------------------------------------------------------------
                    | Complete Response
                    |--------------------------------------------------------------------------
                    */

                    'raw_data' => $chart,
                ]
            );

            Log::info('Horoscope Generated Successfully', [
                'user_id' => $user->id,
                'place' => $place,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

        } catch (\Throwable $e) {

            Log::error('Horoscope Generation Failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
        }
    }
}