<?php

namespace Tests\Fakes;

use App\Services\Ai\LlmClient;
use App\Services\Ai\LlmResponse;

class FakeLlmClient implements LlmClient
{
    public array $generated = [];
    public array $chats = [];

    /** @var LlmResponse[] Scripted replies for chat(), used in order. */
    public array $queue = [];

    public function __construct(public string $text = 'Fake AI text.')
    {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $this->generated[] = ['system' => $systemPrompt, 'user' => $userPrompt];

        return $this->text;
    }

    public function chat(string $systemPrompt, array $messages, array $tools = []): LlmResponse
    {
        $this->chats[] = ['system' => $systemPrompt, 'messages' => $messages, 'tools' => $tools];

        return array_shift($this->queue) ?? new LlmResponse(text: $this->text);
    }
}