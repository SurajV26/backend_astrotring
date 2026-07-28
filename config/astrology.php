<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chart Mapping
    |--------------------------------------------------------------------------
    */

    'charts' => [

        'D1' => [
            'name' => 'Rasi Chart (D1)',
            'paths' => [
                'horoscope.d1',
                'horoscope.rasi_chart',
                'horoscope.birth_chart',
                'horoscope.main_chart',
            ],
        ],

        'D2' => [
            'name' => 'Hora Chart (D2)',
            'paths' => [
                'horoscope.d2',
                'horoscope.hora_chart',
            ],
        ],

        'D4' => [
            'name' => 'Chaturthamsha (D4)',
            'paths' => [
                'horoscope.d4',
            ],
        ],

        'D6' => [
            'name' => 'Shashtamsha (D6)',
            'paths' => [
                'horoscope.d6',
            ],
        ],

        'D7' => [
            'name' => 'Saptamsha (D7)',
            'paths' => [
                'horoscope.d7',
            ],
        ],

        'D9' => [
            'name' => 'Navamsa (D9)',
            'paths' => [
                'horoscope.d9',
                'horoscope.navamsa',
            ],
        ],

        'D10' => [
            'name' => 'Dashamsa (D10)',
            'paths' => [
                'horoscope.d10',
            ],
        ],

        'D12' => [
            'name' => 'Dwadasamsa (D12)',
            'paths' => [
                'horoscope.d12',
            ],
        ],

        'D16' => [
            'name' => 'Shodashamsa (D16)',
            'paths' => [
                'horoscope.d16',
            ],
        ],

        'D20' => [
            'name' => 'Vimsamsa (D20)',
            'paths' => [
                'horoscope.d20',
            ],
        ],

        'D24' => [
            'name' => 'Chaturvimshamsa (D24)',
            'paths' => [
                'horoscope.d24',
            ],
        ],

        'D27' => [
            'name' => 'Bhamsa (D27)',
            'paths' => [
                'horoscope.d27',
            ],
        ],

        'D30' => [
            'name' => 'Trimshamsa (D30)',
            'paths' => [
                'horoscope.d30',
            ],
        ],

        'D40' => [
            'name' => 'Khavedamsa (D40)',
            'paths' => [
                'horoscope.d40',
            ],
        ],

        'D45' => [
            'name' => 'Akshavedamsa (D45)',
            'paths' => [
                'horoscope.d45',
            ],
        ],

        'D60' => [
            'name' => 'Shastiamsa (D60)',
            'paths' => [
                'horoscope.d60',
            ],
        ],

        'Dasha' => [
            'name' => 'Dasha',
            'paths' => [
                'horoscope.dasha',
                'horoscope.vimshottari_dasha',
            ],
        ],

        'Transit' => [
            'name' => 'Transit',
            'paths' => [
                'horoscope.transit',
                'horoscope.gochar',
            ],
        ],

        'Panchang' => [
            'name' => 'Panchang',
            'paths' => [
                'horoscope.calendar_info',
                'horoscope.panchang',
            ],
        ],

        'Muhurat' => [
            'name' => 'Muhurat',
            'paths' => [
                'horoscope.muhurat',
            ],
        ],

        'Numerology' => [
            'name' => 'Numerology',
            'paths' => [
                'numerology',
            ],
        ],

        'Nakshatra Analysis' => [
            'name' => 'Nakshatra',
            'paths' => [
                'horoscope.nakshatra',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Always Include
    |--------------------------------------------------------------------------
    */

    'always' => [

        'birth_details',

        'horoscope.planets',

        'horoscope.planet_positions',

        'horoscope.ascendant',

        'horoscope.lagna',

        'horoscope.moon',

        'horoscope.sun',

        'horoscope.yogas',

        'horoscope.doshas',

    ],

];