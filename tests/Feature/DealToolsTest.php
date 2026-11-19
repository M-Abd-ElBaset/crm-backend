<?php

namespace Tests\Feature;

use App\Enums\DealStage;
use App\Models\Customer;
use App\Services\Ai\DealTools;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesKnownDeals;
use Tests\TestCase;

class DealToolsTest extends TestCase
{
    use RefreshDatabase, CreatesKnownDeals;

    private DealTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new DealTools();
    }

    public function test_top_open_deals_are_sorted_and_exclude_won_and_lost(): void
    {
        $c = Customer::factory()->create();
        $this->makeDeal($c, 'Mid', '500.50', DealStage::Lead);
        $this->makeDeal($c, 'Big', '900.00', DealStage::Negotiation);
        $this->makeDeal($c, 'Small', '100.00', DealStage::Proposal);
        $this->makeDeal($c, 'Won', '5000.00', DealStage::Won);
        $this->makeDeal($c, 'Lost', '7000.00', DealStage::Lost, reason: 'Budget');

        $result = $this->tools->execute('top_open_deals', ['limit' => 2]);

        $this->assertSame(['Big', 'Mid'], array_column($result['deals'], 'title'));
        $this->assertSame('900.00', $result['deals'][0]['value']);
    }

    public function test_the_limit_is_capped_and_never_below_one(): void
    {
        $c = Customer::factory()->create();
        foreach (range(1, 12) as $i) {
            $this->makeDeal($c, "Deal {$i}", (string) ($i * 10) . '.00', DealStage::Lead);
        }

        $this->assertCount(10, $this->tools->execute('top_open_deals', ['limit' => 999])['deals']);
        $this->assertCount(1, $this->tools->execute('top_open_deals', ['limit' => 0])['deals']);
        $this->assertCount(1, $this->tools->execute('top_open_deals', ['limit' => -5])['deals']);
    }

    public function test_total_value_by_stage_is_summed_by_the_database(): void
    {
        $c = Customer::factory()->create();
        $this->makeDeal($c, 'One', '100.10', DealStage::Negotiation);
        $this->makeDeal($c, 'Two', '200.20', DealStage::Negotiation);
        $this->makeDeal($c, 'Other', '999.00', DealStage::Lead);

        $result = $this->tools->execute('total_value_by_stage', ['stage' => 'negotiation']);

        $this->assertSame(2, $result['count']);
        $this->assertSame('300.30', $result['total']);
    }

    public function test_an_invalid_stage_returns_an_error_instead_of_throwing(): void
    {
        $this->assertArrayHasKey('error', $this->tools->execute('total_value_by_stage', ['stage' => 'banana']));
        $this->assertArrayHasKey('error', $this->tools->execute('total_value_by_stage', []));
    }

    public function test_deals_closing_in_month_flags_overdue_deals(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));

        $c = Customer::factory()->create();
        $this->makeDeal($c, 'Past', '100.00', DealStage::Lead, '2026-11-05');
        $this->makeDeal($c, 'Later', '200.00', DealStage::Lead, '2026-11-25');
        $this->makeDeal($c, 'Next month', '300.00', DealStage::Lead, '2026-12-02');
        $this->makeDeal($c, 'Already won', '400.00', DealStage::Won, '2026-11-10');

        $result = $this->tools->execute('deals_closing_in_month', []);

        $this->assertSame('2026-11', $result['month']);
        $this->assertSame(2, $result['summary']['count']);
        $this->assertSame('300.00', $result['summary']['total']);

        $byTitle = collect($result['deals'])->keyBy('title');
        $this->assertTrue($byTitle['Past']['is_overdue']);
        $this->assertFalse($byTitle['Later']['is_overdue']);
    }

    public function test_an_invalid_month_returns_an_error(): void
    {
        $this->assertArrayHasKey('error', $this->tools->execute('deals_closing_in_month', ['month' => '2026-13']));
        $this->assertArrayHasKey('error', $this->tools->execute('deals_closing_in_month', ['month' => 'banana']));
    }

    public function test_customers_are_ranked_by_active_deals_only(): void
    {
        $a = Customer::factory()->create(['name' => 'A']);
        $b = Customer::factory()->create(['name' => 'B']);

        $this->makeDeal($a, 'A1', '100.00', DealStage::Lead);
        $this->makeDeal($a, 'A2', '200.00', DealStage::Proposal);
        $this->makeDeal($a, 'A won', '999.00', DealStage::Won); // must not count
        $this->makeDeal($b, 'B1', '5000.00', DealStage::Lead);

        $result = $this->tools->execute('customers_by_active_deals', []);

        $this->assertSame('A', $result['customers'][0]['name']);
        $this->assertSame(2, $result['customers'][0]['active_deals']);
        $this->assertSame('300.00', $result['customers'][0]['active_value']);
        $this->assertSame(1, $result['customers'][1]['active_deals']);
    }

    public function test_lost_deal_reasons_are_grouped_and_missing_reasons_are_labelled(): void
    {
        $c = Customer::factory()->create();
        $this->makeDeal($c, 'L1', '10.00', DealStage::Lost, reason: 'Budget');
        $this->makeDeal($c, 'L2', '20.00', DealStage::Lost, reason: 'Budget');
        $this->makeDeal($c, 'L3', '30.00', DealStage::Lost, reason: 'Competitor');
        $this->makeDeal($c, 'L4', '40.00', DealStage::Lost);

        $result = $this->tools->execute('lost_deal_reasons', []);

        $this->assertSame(4, $result['count']);
        $this->assertSame(2, $result['by_reason']['Budget']);
        $this->assertSame(1, $result['by_reason']['No reason recorded']);
    }

    public function test_an_unknown_tool_returns_an_error(): void
    {
        $this->assertArrayHasKey('error', $this->tools->execute('drop_tables', []));
    }

    public function test_no_tool_exposes_customer_email_or_phone(): void
    {
        $c = Customer::factory()->create(['email' => 'secret@example.com', 'phone' => '555-0100-SECRET']);
        $this->makeDeal($c, 'Open', '100.00', DealStage::Lead, now()->toDateString());
        $this->makeDeal($c, 'Lost', '50.00', DealStage::Lost, reason: 'Budget');

        $output = json_encode([
            $this->tools->execute('top_open_deals', []),
            $this->tools->execute('deals_closing_in_month', []),
            $this->tools->execute('total_value_by_stage', ['stage' => 'lead']),
            $this->tools->execute('customers_by_active_deals', []),
            $this->tools->execute('lost_deal_reasons', []),
        ]);

        $this->assertStringNotContainsString('secret@example.com', $output);
        $this->assertStringNotContainsString('555-0100-SECRET', $output);
    }
}