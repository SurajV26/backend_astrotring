<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAiService
{
    public function chat(array $messages): string
    {
        Log::info('OPENAI REQUEST', [
            'model' => 'gpt-4.1-mini',
            'messages_count' => count($messages),
            'system_prompt_chars' => strlen($messages[0]['content'] ?? ''),
            'total_request_chars' => strlen(json_encode($messages)),
            'approx_tokens' => ceil(strlen(json_encode($messages)) / 4),
        ]);
    
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4.1-mini',
            'messages' => $messages,
            'temperature' => 1,
        ]);
    
        return trim($response->choices[0]->message->content ?? '');
    }
}