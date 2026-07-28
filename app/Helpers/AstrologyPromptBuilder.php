<?php

namespace App\Helpers;

use App\Models\AiAstrologerExpertise;

class AstrologyPromptBuilder
{
    /**
     * Build final prompt for AI.
     */
    public static function build(
        array $rawData,
        AiAstrologerExpertise $expertise,
        array $birthDetails = [],
        string $question = ''
    ): string {

        $charts = json_decode(
            $expertise->relevant_chart,
            true
        ) ?? [];

        /*
        |--------------------------------------------------------------------------
        | Extract Only Required Horoscope
        |--------------------------------------------------------------------------
        */

        $knowledge = AstrologyKnowledgeBuilder::build(
            $rawData,
            $expertise->slug
        );

        /*
        |--------------------------------------------------------------------------
        | Build Prompt
        |--------------------------------------------------------------------------
        */

        $prompt = [];

        $prompt[] = "You are a highly experienced Vedic astrologer.";

        $prompt[] = "Never guess.";

        $prompt[] = "Only use the horoscope data provided below.";

        $prompt[] = "If data is missing say it is unavailable.";

        $prompt[] = "";

        $prompt[] = "==================================================";

        $prompt[] = "EXPERTISE";

        $prompt[] = $expertise->name;

        $prompt[] = "==================================================";

        /*
        |--------------------------------------------------------------------------
        | Birth Details
        |--------------------------------------------------------------------------
        */

        if (!empty($birthDetails)) {

            $prompt[] = "";

            $prompt[] = "BIRTH DETAILS";

            foreach ($birthDetails as $key => $value) {

                $prompt[] = ucfirst(str_replace('_', ' ', $key)) . ": " . $value;

            }
        }

        /*
        |--------------------------------------------------------------------------
        | Horoscope
        |--------------------------------------------------------------------------
        */

        $prompt[] = "";

        $prompt[] = "==================================================";

        $prompt[] = "HOROSCOPE DATA";

        $prompt[] = "==================================================";

        $prompt[] = json_encode(

            $horoscope,

            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE

        );

        /*
        |--------------------------------------------------------------------------
        | Rules
        |--------------------------------------------------------------------------
        */

        $prompt[] = "";

        $prompt[] = "==================================================";

        $prompt[] = "ANSWER RULES";

        $prompt[] = "==================================================";

        $prompt[] = "- Answer only according to Vedic astrology.";

        $prompt[] = "- Don't invent yogas.";

        $prompt[] = "- Don't invent doshas.";

        $prompt[] = "- Don't assume planets.";

        $prompt[] = "- Explain reasoning.";

        $prompt[] = "- Mention chart names whenever needed.";

        $prompt[] = "- If multiple charts support the same conclusion mention them.";

        $prompt[] = "- If prediction confidence is low clearly mention it.";

        $prompt[] = "- Keep language simple.";

        $prompt[] = "- Give practical remedies only if horoscope supports them.";

        /*
        |--------------------------------------------------------------------------
        | User Question
        |--------------------------------------------------------------------------
        */

        $prompt[] = "";

        $prompt[] = "==================================================";

        $prompt[] = "QUESTION";

        $prompt[] = "==================================================";

        $prompt[] = $question;

        return implode("\n", $prompt);

    }
}