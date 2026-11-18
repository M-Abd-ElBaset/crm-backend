<?php

namespace App\Services\Ai;

interface LlmClient
{
    public function generate(string $systemPrompt, string $userPrompt): string;
}