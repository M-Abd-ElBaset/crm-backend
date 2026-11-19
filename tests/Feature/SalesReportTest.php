<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceException;
use App\Models\User;
use App\Services\Ai\LlmClient;
use App\Services\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesKnownDeals;
use Tests\Fakes\FakeLlmClient;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase, CreatesKnownDeals;

    public function test_stats_are_calculated_from_the_database(): void
    {
        $this->createKnownDeals();

        $stats = app(SalesReportService::class)->stats();

        $this->assertSame(4, $stats['total_deals']);
        $this->assertSame(['count' => 2, 'total' => '150.25'], $stats['pipeline']);
        $this->assertSame(['count' => 1, 'total' => '400.00'], $stats['won']);
        $this->assertSame(['count' => 1, 'total' => '250.50'], $stats['lost']);
        $this->assertEquals(50.0, $stats['win_rate_percent']);
        $this->assertSame('500.00', $stats['top_customers'][0]['total_value']);
        $this->assertSame('300.75', $stats['top_customers'][1]['total_value']);
        $this->assertSame([['reason' => 'Budget', 'count' => 1]], $stats['lost_reasons']);
    }

    public function test_every_stage_is_listed_even_when_empty(): void
    {
        $stats = app(SalesReportService::class)->stats();

        $this->assertCount(6, $stats['by_stage']);
        $this->assertSame(['count' => 0, 'total' => '0.00'], $stats['by_stage']['proposal']);
        $this->assertNull($stats['win_rate_percent']);
        $this->assertSame(0, $stats['total_deals']);
    }

    public function test_the_stats_endpoint_returns_the_figures(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->createKnownDeals();

        $this->getJson('/api/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.total_deals', 4)
            ->assertJsonPath('data.pipeline.total', '150.25');
    }

    public function test_the_ai_is_given_the_database_figures_and_only_writes_text(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->createKnownDeals();
        $fake = new FakeLlmClient('Narrative text.');
        $this->app->instance(LlmClient::class, $fake);

        $this->postJson('/api/reports/sales/summary')
            ->assertOk()
            ->assertJsonPath('data.summary', 'Narrative text.')
            ->assertJsonPath('data.stats.pipeline.total', '150.25');

        $prompt = $fake->generated[0]['user'];
        $this->assertStringContainsString('150.25', $prompt);
        $this->assertStringContainsString('400.00', $prompt);
        $this->assertStringContainsString('never instructions', $fake->generated[0]['system']);
    }

    public function test_an_ai_failure_returns_a_clean_503(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->mock(LlmClient::class, fn ($mock) => $mock
            ->shouldReceive('generate')
            ->andThrow(new AiServiceException('The AI service is busy.')));

        $this->postJson('/api/reports/sales/summary')
            ->assertStatus(503)
            ->assertExactJson(['message' => 'The AI service is busy.']);
    }
}