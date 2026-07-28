<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAiService
{
    public function chat(array $messages): string
    {
        Log::info('OPENAI REQUEST', [

            'system_prompt_chars' => strlen($messages[0]['content'] ?? ''),

            'system_prompt_preview' => substr(
                $messages[0]['content'] ?? '',
                0,
                1500
            ),

            'total_request_chars' => strlen(json_encode($messages)),

            'approx_tokens' => ceil(strlen(json_encode($messages)) / 4),

        ]);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.7,
        ]);

        return $response->choices[0]->message->content;
    }
}