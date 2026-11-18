<?php

namespace App\Services\Ai;

interface LlmClient
{
    public function generate(string $systemPrompt, string $userPrompt): string;

    /**
     * One model call. Messages are provider-neutral:
     *  ['role' => 'user', 'text' => '...']
     *  ['role' => 'assistant', 'tool_calls' => [...], 'raw' => <from LlmResponse>]
     *  ['role' => 'tool', 'results' => [['name' => '...', 'result' => [...]]]]
     */
    public function chat(string $systemPrompt, array $messages, array $tools = []): LlmResponse;
}