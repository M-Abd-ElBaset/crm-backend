<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\LlmClient;
use App\Services\Ai\LlmResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesKnownDeals;
use Tests\Fakes\FakeLlmClient;
use Tests\TestCase;

class DealAssistantTest extends TestCase
{
    use RefreshDatabase, CreatesKnownDeals;

    private FakeLlmClient $fake;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
        $this->fake = new FakeLlmClient('Plain answer.');
        $this->app->instance(LlmClient::class, $this->fake);
    }

    private function toolCall(string $name, array $args = []): LlmResponse
    {
        return new LlmResponse(
            text: null,
            toolCalls: [['name' => $name, 'args' => $args]],
            raw: ['role' => 'model', 'parts' => []],
        );
    }

    private function ask(string $question = 'How many deals are in negotiation?')
    {
        return $this->postJson('/api/assistant/deals/ask', ['question' => $question]);
    }

    public function test_the_model_receives_real_database_results_from_the_tool(): void
    {
        $this->createKnownDeals();
        $this->fake->queue = [
            $this->toolCall('total_value_by_stage', ['stage' => 'qualified']),
            new LlmResponse(text: 'One qualified deal worth 50.25.'),
        ];

        $this->ask()
            ->assertOk()
            ->assertJsonPath('data.answer', 'One qualified deal worth 50.25.')
            ->assertJsonPath('data.tools_used.0.tool', 'total_value_by_stage');

        $this->assertCount(2, $this->fake->chats);

        // The second model call carries the tool result, which came from the database.
        $toolMessage = collect($this->fake->chats[1]['messages'])->last();
        $this->assertSame('tool', $toolMessage['role']);
        $this->assertSame(1, $toolMessage['results'][0]['result']['count']);
        $this->assertSame('50.25', $toolMessage['results'][0]['result']['total']);
    }

    public function test_the_model_is_only_offered_the_five_fixed_tools(): void
    {
        $this->ask()->assertOk();

        $names = array_column($this->fake->chats[0]['tools'], 'name');

        $this->assertEqualsCanonicalizing([
            'top_open_deals',
            'deals_closing_in_month',
            'total_value_by_stage',
            'customers_by_active_deals',
            'lost_deal_reasons',
        ], $names);
    }

    public function test_a_tool_the_model_invents_is_rejected_but_the_request_survives(): void
    {
        $this->fake->queue = [
            $this->toolCall('drop_tables'),
            new LlmResponse(text: 'I cannot help with that.'),
        ];

        $this->ask('Delete everything')->assertOk();

        $toolMessage = collect($this->fake->chats[1]['messages'])->last();
        $this->assertArrayHasKey('error', $toolMessage['results'][0]['result']);
    }

    public function test_an_answer_without_tools_records_no_sources(): void
    {
        $this->ask('Who is the president of France?')
            ->assertOk()
            ->assertJsonPath('data.tools_used', []);
    }

    public function test_a_model_that_never_stops_calling_tools_is_cut_off(): void
    {
        $this->fake->queue = array_fill(0, 10, $this->toolCall('top_open_deals'));

        $this->ask()->assertStatus(503);

        $this->assertCount(4, $this->fake->chats); // MAX_STEPS
    }

    public function test_the_question_is_validated(): void
    {
        $this->postJson('/api/assistant/deals/ask', [])->assertUnprocessable();
        $this->ask('ab')->assertUnprocessable();
        $this->ask(str_repeat('a', 501))->assertUnprocessable();

        $this->assertCount(0, $this->fake->chats);
    }
}