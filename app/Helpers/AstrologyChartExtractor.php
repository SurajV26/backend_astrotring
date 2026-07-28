<?php

namespace App\Helpers;

use Illuminate\Support\Arr;

class AstrologyChartExtractor
{
    /**
     * Extract relevant horoscope data.
     */
    public static function extract(array $rawData, array $requiredCharts): array
    {
        $config = config('astrology');

        $result = [];

        /*
        |--------------------------------------------------------------------------
        | Always Include
        |--------------------------------------------------------------------------
        */

        foreach ($config['always'] as $path) {

            $value = data_get($rawData, $path);

            if (!empty($value)) {
                data_set($result, $path, $value);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Requested Charts
        |--------------------------------------------------------------------------
        */

        foreach ($requiredCharts as $chart) {

            $definition = $config['charts'][$chart] ?? null;

            if (!$definition) {
                continue;
            }

            foreach ($definition['paths'] as $path) {

                $value = data_get($rawData, $path);

                if (empty($value)) {
                    continue;
                }

                $result['charts'][$chart] = [
                    'name' => $definition['name'],
                    'data' => $value,
                ];

                break;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Remove Empty Values
        |--------------------------------------------------------------------------
        */

        return self::clean($result);
    }

    /**
     * Remove null & empty values recursively.
     */
    private static function clean($array)
    {
        if (!is_array($array)) {
            return $array;
        }

        foreach ($array as $key => $value) {

            if (is_array($value)) {

                $array[$key] = self::clean($value);

                if (empty($array[$key])) {
                    unset($array[$key]);
                }

                continue;
            }

            if (
                $value === null ||
                $value === '' ||
                $value === []
            ) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Convert extracted data to JSON.
     */
    public static function toJson(array $rawData, array $requiredCharts): string
    {
        return json_encode(

            self::extract(
                $rawData,
                $requiredCharts
            ),

            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES

        );
    }
}