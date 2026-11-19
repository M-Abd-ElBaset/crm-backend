<?php

namespace Tests\Unit;

use App\Exceptions\AiServiceException;
use App\Services\Ai\GeminiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'test-model']);
        Sleep::fake(); // retries must not really wait
    }

    private function textReply(string $text): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]]];
    }

    public function test_generate_returns_the_text_and_sends_the_key_in_a_header(): void
    {
        Http::fake(['*' => Http::response($this->textReply('Hello there'))]);

        $this->assertSame('Hello there', (new GeminiClient())->generate('sys', 'hi'));

        Http::assertSent(fn (Request $r) => $r->hasHeader('x-goog-api-key', 'test-key')
            && ! str_contains($r->url(), 'test-key')
            && str_contains($r->url(), 'test-model:generateContent'));
    }

    public function test_thought_parts_are_not_returned(): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [
            ['text' => 'internal reasoning', 'thought' => true],
            ['text' => 'Final answer'],
        ]]]]])]);

        $this->assertSame('Final answer', (new GeminiClient())->generate('sys', 'hi'));
    }

    public function test_a_missing_key_fails_before_any_request(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('AI provider is not configured.');

        try {
            (new GeminiClient())->generate('sys', 'hi');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_an_overloaded_provider_is_retried_then_reported_cleanly(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'internal detail']], 503)]);

        try {
            (new GeminiClient())->generate('sys', 'hi');
            $this->fail('Expected an AiServiceException.');
        } catch (AiServiceException $e) {
            $this->assertSame('The AI service is overloaded. Please try again shortly.', $e->getMessage());
            $this->assertStringNotContainsString('internal detail', $e->getMessage());
        }

        Http::assertSentCount(2); // first attempt plus one retry
    }

    public function test_a_retry_can_succeed(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['error' => []], 503)
            ->push($this->textReply('Recovered'))]);

        $this->assertSame('Recovered', (new GeminiClient())->generate('sys', 'hi'));
        Http::assertSentCount(2);
    }

    public function test_a_rate_limit_has_its_own_message(): void
    {
        Http::fake(['*' => Http::response([], 429)]);

        $this->expectExceptionMessage('The AI service is busy. Please try again shortly.');

        (new GeminiClient())->generate('sys', 'hi');
    }

    public function test_an_empty_model_reply_is_an_error(): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => []]]]])]);

        $this->expectException(AiServiceException::class);

        (new GeminiClient())->generate('sys', 'hi');
    }

    public function test_empty_tool_arguments_and_thought_signatures_survive_the_round_trip(): void
    {
        // Regression test for the 400 error: PHP turned {} into [] when sending the call back.
        Http::fake(['*' => Http::sequence()
            ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [[
                'functionCall' => ['name' => 'lost_deal_reasons', 'args' => (object) []],
                'thoughtSignature' => 'sig-123',
            ]]]]]])
            ->push($this->textReply('Done'))]);

        $client = new GeminiClient();
        $user = ['role' => 'user', 'text' => 'Why were deals lost?'];

        $first = $client->chat('sys', [$user], [['name' => 'lost_deal_reasons', 'description' => 'x']]);

        $this->assertTrue($first->hasToolCalls());
        $this->assertSame('lost_deal_reasons', $first->toolCalls[0]['name']);

        $second = $client->chat('sys', [
            $user,
            ['role' => 'assistant', 'tool_calls' => $first->toolCalls, 'raw' => $first->raw],
            ['role' => 'tool', 'results' => [['name' => 'lost_deal_reasons', 'result' => ['count' => 0]]]],
        ]);

        $this->assertSame('Done', $second->text);

        Http::assertSent(fn (Request $r) => str_contains($r->body(), '"args":{}')
            && str_contains($r->body(), 'sig-123'));
    }
}