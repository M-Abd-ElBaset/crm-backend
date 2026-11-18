<?php

namespace App\Services\Ai;

final class LlmResponse
{
    /**
     * @param  array<int, array{name: string, args: array}>  $toolCalls
     * @param  mixed  $raw  Provider-specific data. Pass it back unchanged in the next assistant message.
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $toolCalls = [],
        public readonly mixed $raw = null,
    ) {
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}