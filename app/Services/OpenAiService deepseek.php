<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAiService
{
    public function chat(array $messages): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => 'deepseek/deepseek-chat',
            'messages' => $messages,
            'temperature' => 0,
        ]);

        if (!$response->successful()) {
            throw new \Exception(
                $response->json('error.message')
                ?? 'AI service error'
            );
        }

        return trim($response->json(
            'choices.0.message.content'
        ));
    }
}