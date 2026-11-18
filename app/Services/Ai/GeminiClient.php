<?php

namespace App\Services\Ai;

use App\Exceptions\AiServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class GeminiClient implements LlmClient
{
    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $data = $this->post([
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                    'generationConfig' => $this->generationConfig(),
                ]);

        return $this->extractText(data_get($data, 'candidates.0.content.parts', []));
    }

    public function chat(string $systemPrompt, array $messages, array $tools = []): LlmResponse
    {
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => array_map(fn (array $m) => $this->toContent($m), $messages),
            'generationConfig' => $this->generationConfig(),
        ];

        if ($tools) {
            $payload['tools'] = [['functionDeclarations' => $tools]];
        }

        $content = data_get($this->post($payload), 'candidates.0.content', []);
        $parts = $content['parts'] ?? [];

        $calls = collect($parts)
            ->filter(fn ($part) => isset($part['functionCall']))
            ->map(fn ($part) => [
                'name' => $part['functionCall']['name'],
                'args' => (array) ($part['functionCall']['args'] ?? []),
            ])
            ->values()
            ->all();

        return new LlmResponse(
            text: $calls ? null : $this->extractText($parts),
            toolCalls: $calls,
            raw: $content,
        );
    }

    private function toContent(array $message): array
    {
        return match ($message['role']) {
            'user' => ['role' => 'user', 'parts' => [['text' => $message['text']]]],
            // The model's own content, untouched, so thought signatures are preserved.
            'assistant' => $this->restoreEmptyArgs($message['raw']),
            'tool' => ['role' => 'user', 'parts' => array_map(
                fn (array $r) => ['functionResponse' => [
                    'name' => $r['name'],
                    'response' => ['result' => $r['result']],
                ]],
                $message['results'],
            )],
        };
    }

    /** PHP turns an empty JSON object into []. Put it back, because Gemini needs an object. */
    private function restoreEmptyArgs(array $content): array
    {
        $content['parts'] = array_map(function (array $part) {
            if (isset($part['functionCall']) && empty($part['functionCall']['args'])) {
                $part['functionCall']['args'] = new \stdClass();
            }

            return $part;
        }, $content['parts'] ?? []);

        return $content;
    }

    private function generationConfig(): array
    {
        return [
            'temperature' => 0.2,
            'thinkingConfig' => ['thinkingLevel' => 'low'],
        ];
    }

    private function extractText(array $parts): string
    {
        $text = collect($parts)
            ->filter(fn ($part) => empty($part['thought']))
            ->pluck('text')
            ->implode('');

        if (trim($text) === '') {
            throw new AiServiceException('The AI service returned an empty response.');
        }

        return $text;
    }

    private function post(array $payload): array
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
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->timeout(60)
                ->retry(
                    2,
                    1500,
                    fn ($e) => $e instanceof RequestException
                        && in_array($e->response->status(), [429, 503], true),
                    throw: false,
                )
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error('Gemini connection failed', ['error' => $e->getMessage()]);
            throw new AiServiceException('The AI service is unreachable.', 0, $e);
        }

        if ($response->failed()) {
            Log::error('Gemini request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AiServiceException(match ($response->status()) {
                429 => 'The AI service is busy. Please try again shortly.',
                503 => 'The AI service is overloaded. Please try again shortly.',
                default => 'The AI service returned an error.',
            });
        }

        return $response->json() ?? [];
    }
}