<?php

namespace App\Helpers;

class AstrologyKnowledgeBuilder
{
    public static function build(array $rawData, string $expertise): string
    {
        $knowledge = [];

        $knowledge[] = self::basicProfile($rawData);

        $knowledge[] = self::planetStates($rawData);

        $knowledge[] = self::karakas($rawData);

        $knowledge[] = self::doshas($rawData);

        switch (strtolower($expertise)) {

            case 'love-marriage-relationships':
                $knowledge[] = self::loveKnowledge($rawData);
                break;

            case 'career-profession-public-success':
                $knowledge[] = self::careerKnowledge($rawData);
                break;

            case 'finance-wealth-business-growth':
                $knowledge[] = self::financeKnowledge($rawData);
                break;

            case 'health-mental-peace-healing':
                $knowledge[] = self::healthKnowledge($rawData);
                break;

            case 'children-family-ancestral-karma':
                $knowledge[] = self::familyKnowledge($rawData);
                break;

            default:
                $knowledge[] = self::generalKnowledge($rawData);
        }

        return implode("\n\n", array_filter($knowledge));
    }

    protected static function basicProfile(array $raw): string
    {
        $birth = $raw['birth_details'] ?? [];

        $d1 = $raw['horoscope']['divisional_charts']['D-1_rasi'] ?? [];

        $asc = $d1['Ascendant']['sign'] ?? '';

        $moon = $d1['Moon']['sign'] ?? '';

        $sun = $d1['Sun']['sign'] ?? '';

        return
            "===== BASIC PROFILE =====\n".
            "DOB : ".($birth['date'] ?? '')."\n".
            "TIME : ".($birth['time'] ?? '')."\n".
            "PLACE : ".($birth['place'] ?? '')."\n".
            "ASCENDANT : ".$asc."\n".
            "MOON SIGN : ".$moon."\n".
            "SUN SIGN : ".$sun;
    }

    protected static function planetStates(array $raw): string
    {
        $states = $raw['horoscope']['planetary_states'] ?? [];

        return
            "===== PLANET STATES =====\n".
            "Retrograde : ".implode(', ', $states['retrograde_planets'] ?? [])."\n".
            "Combusted : ".implode(', ', $states['combusted_planets'] ?? [])."\n".
            "Exalted : ".implode(', ', $states['exalted_planets'] ?? [])."\n".
            "Debilitated : ".implode(', ', $states['debilitated_planets'] ?? [])."\n".
            "Own Sign : ".implode(', ', $states['own_sign_planets'] ?? [])."\n".
            "Friend Sign : ".implode(', ', $states['friend_sign_planets'] ?? [])."\n".
            "Enemy Sign : ".implode(', ', $states['enemy_sign_planets'] ?? []);
    }

    protected static function karakas(array $raw): string
    {
        $karakas = $raw['horoscope']['chara_karakas'] ?? [];

        $text = "===== CHARA KARAKAS =====\n";

        foreach ($karakas as $name => $planet) {

            $text .= strtoupper(str_replace('_',' ',$name));

            $text .= " : ";

            $text .= ($planet['planet'] ?? '');

            $text .= " (";

            $text .= ($planet['sign'] ?? '');

            $text .= ")\n";
        }

        return trim($text);
    }

    protected static function doshas(array $raw): string
    {
        $doshas = $raw['horoscope']['doshas'] ?? [];

        $text = "===== DOSHAS =====\n";

        foreach ($doshas as $name => $value) {

            $value = strip_tags($value);

            $value = preg_replace('/\s+/', ' ', $value);

            $text .= $name." : ".$value."\n";
        }

        return trim($text);
    }

        protected static function loveKnowledge(array $raw): string
    {
        $d1 = $raw['horoscope']['divisional_charts']['D-1_rasi'] ?? [];
        $d9 = $raw['horoscope']['divisional_charts']['D-9_navamsa'] ?? [];

        $venus = $d1['Venus'] ?? [];
        $mars = $d1['Mars'] ?? [];
        $moon = $d1['Moon'] ?? [];

        $doshas = $raw['horoscope']['doshas'] ?? [];

        $yogas = $raw['horoscope']['yogas']['yoga_list'] ?? [];

        $text = "===== LOVE & MARRIAGE =====\n";

        $text .= "D1 Venus : "
            .($venus['sign'] ?? '')
            ." "
            .($venus['longitude'] ?? '')
            ."\n";

        $text .= "D1 Mars : "
            .($mars['sign'] ?? '')
            ." "
            .($mars['longitude'] ?? '')
            ."\n";

        $text .= "Moon : "
            .($moon['sign'] ?? '')
            ."\n\n";

        $text .= "D9 Navamsa\n";

        foreach ($d9 as $planet => $data) {

            $text .= $planet
                ." : "
                .($data['sign'] ?? '')
                ." "
                .($data['longitude'] ?? '')
                ."\n";
        }

        $text .= "\nMarriage Related Doshas\n";

        foreach ([
            'Manglik Dosha',
            'Kalathra Dosha',
            'Pitru Dosha',
            'Guru Chandala Dosha'
        ] as $name){

            if(isset($doshas[$name])){

                $text .= $name." : ";

                $text .= trim(strip_tags($doshas[$name]));

                $text .= "\n";
            }

        }

        $text .= "\nMarriage Yogas\n";

        foreach($yogas as $key=>$yoga){

            $title = strtolower($yoga[1] ?? '');

            if(
                str_contains($title,'marriage') ||
                str_contains($title,'kalatra') ||
                str_contains($title,'wife') ||
                str_contains($title,'puthra') ||
                str_contains($title,'love')
            ){

                $text .= "- ".$yoga[1]."\n";

            }

        }

        return trim($text);
    }

    protected static function careerKnowledge(array $raw): string
    {
        $d10 = $raw['horoscope']['divisional_charts']['D-10_dasamsa'] ?? [];

        $states = $raw['horoscope']['planetary_states'] ?? [];

        $yogas = $raw['horoscope']['yogas']['yoga_list'] ?? [];

        $text = "===== CAREER =====\n";

        $text .= "D10 Dasamsa\n";

        foreach($d10 as $planet=>$data){

            $text .= $planet
                ." : "
                .($data['sign'] ?? '')
                ." "
                .($data['longitude'] ?? '')
                ."\n";
        }

        $text .= "\nPlanet Strength\n";

        $text .= "Retrograde : ".implode(', ',$states['retrograde_planets'] ?? [])."\n";

        $text .= "Combusted : ".implode(', ',$states['combusted_planets'] ?? [])."\n";

        $text .= "Own Sign : ".implode(', ',$states['own_sign_planets'] ?? [])."\n";

        $text .= "Exalted : ".implode(', ',$states['exalted_planets'] ?? [])."\n";

        $text .= "\nCareer Yogas\n";

        foreach($yogas as $yoga){

            $name = strtolower($yoga[1] ?? '');

            if(
                str_contains($name,'raja') ||
                str_contains($name,'karma') ||
                str_contains($name,'rajya') ||
                str_contains($name,'profession') ||
                str_contains($name,'success')
            ){

                $text .= "- ".$yoga[1]."\n";

            }

        }

        return trim($text);
    }

    protected static function financeKnowledge(array $raw): string
    {
        $d2 = $raw['horoscope']['divisional_charts']['D-2_hora'] ?? [];

        $yogas = $raw['horoscope']['yogas']['yoga_list'] ?? [];

        $text = "===== FINANCE =====\n";

        $text .= "Hora Chart\n";

        foreach($d2 as $planet=>$data){

            $text .= $planet
                ." : "
                .($data['sign'] ?? '')
                ." "
                .($data['longitude'] ?? '')
                ."\n";

        }

        $text .= "\nWealth Yogas\n";

        foreach($yogas as $yoga){

            $name = strtolower($yoga[1] ?? '');

            if(
                str_contains($name,'dhana') ||
                str_contains($name,'wealth') ||
                str_contains($name,'lakshmi') ||
                str_contains($name,'finance') ||
                str_contains($name,'prosperity')
            ){

                $text .= "- ".$yoga[1]."\n";

            }

        }

        return trim($text);
    }

    protected static function healthKnowledge(array $raw): string
    {
        $d6 = $raw['horoscope']['divisional_charts']['D-6_shashthamsa'] ?? [];

        $states = $raw['horoscope']['planetary_states'] ?? [];

        $doshas = $raw['horoscope']['doshas'] ?? [];

        $text = "===== HEALTH =====\n";

        $text .= "D6 Shashthamsa\n";

        foreach ($d6 as $planet => $data) {

            $text .= $planet
                ." : "
                .($data['sign'] ?? '')
                ." "
                .($data['longitude'] ?? '')
                ."\n";
        }

        $text .= "\nHealth Related Doshas\n";

        foreach ([
            'Manglik Dosha',
            'Pitru Dosha',
            'Guru Chandala Dosha'
        ] as $name) {

            if (isset($doshas[$name])) {

                $text .= $name." : "
                    .trim(strip_tags($doshas[$name]))
                    ."\n";
            }
        }

        $text .= "\nPlanet Weakness\n";

        $text .= "Debilitated : "
            .implode(', ', $states['debilitated_planets'] ?? [])
            ."\n";

        $text .= "Combusted : "
            .implode(', ', $states['combusted_planets'] ?? [])
            ."\n";

        return trim($text);
    }

    protected static function familyKnowledge(array $raw): string
    {
        $d4 = $raw['horoscope']['divisional_charts']['D-4_chaturthamsa'] ?? [];

        $d12 = $raw['horoscope']['divisional_charts']['D-12_dwadasamsa'] ?? [];

        $karakas = $raw['horoscope']['chara_karakas'] ?? [];

        $text = "===== FAMILY =====\n";

        $text .= "D4 Chaturthamsa\n";

        foreach ($d4 as $planet => $data) {

            $text .= $planet
                ." : "
                .($data['sign'] ?? '')
                ." "
                .($data['longitude'] ?? '')
                ."\n";
        }

        $text .= "\nD12 Dwadasamsa\n";

        foreach ($d12 as $planet => $data) {

            $text .= $planet
                ." : "
                .($data['sign'] ?? '')
                ." "
                .($data['longitude'] ?? '')
                ."\n";
        }

        $text .= "\nFamily Karakas\n";

        foreach ([
            'pitri_karaka',
            'putra_karaka',
            'amatya_karaka'
        ] as $key) {

            if (!isset($karakas[$key])) {
                continue;
            }

            $planet = $karakas[$key];

            $text .= strtoupper(str_replace('_',' ',$key))
                ." : "
                .$planet['planet']
                ." ("
                .$planet['sign']
                .")\n";
        }

        return trim($text);
    }

    protected static function generalKnowledge(array $raw): string
    {
        $summary = $raw['horoscope']['yogas']['summary'] ?? [];

        $text = "===== GENERAL ASTROLOGY =====\n";

        $text .= "Total Yogas : "
            .($summary['total_yogas_found'] ?? 0)
            ."\n";

        $text .= "Raja Yogas : "
            .($summary['total_raja_yogas_found'] ?? 0)
            ."\n\n";

        $text .= self::basicProfile($raw);

        $text .= "\n\n";

        $text .= self::planetStates($raw);

        return trim($text);
    }
}