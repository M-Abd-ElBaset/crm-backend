<?php

namespace App\Services\Ai;

use App\Exceptions\AiServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient implements LlmClient
{
    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $key = config('services.gemini.key');

        if (! $key) {
            throw new AiServiceException('AI provider is not configured.');
        }

        $url = sprintf(
            '%s/models/%s:generateContent',
            config('services.gemini.base_url'),
            config('services.gemini.model'),
        );

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(30)
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                    'generationConfig' => ['temperature' => 0.2],
                ]);
        } catch (ConnectionException $e) {
            Log::error('Gemini connection failed', ['error' => $e->getMessage()]);
            throw new AiServiceException('The AI service is unreachable.', 0, $e);
        }

        if ($response->failed()) {
            Log::error('Gemini request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AiServiceException(
                $response->status() === 429
                    ? 'The AI service is busy. Please try again shortly.'
                    : 'The AI service returned an error.'
            );
        }

        $text = collect(data_get($response->json(), 'candidates.0.content.parts', []))
            ->filter(fn ($part) => empty($part['thought']))
            ->pluck('text')
            ->implode('');

        if (trim($text) === '') {
            throw new AiServiceException('The AI service returned an empty response.');
        }

        return $text;
    }
}