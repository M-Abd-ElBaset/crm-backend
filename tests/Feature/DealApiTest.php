<?php

namespace Tests\Feature;

use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DealApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    private function payload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'title' => 'Annual License',
            'value' => '1500.50',
            'stage' => 'lead',
            'expected_close_date' => '2026-12-31',
            'description' => 'A deal.',
        ], $overrides);
    }

    public function test_index_can_be_filtered_by_stage(): void
    {
        $customer = Customer::factory()->create();
        Deal::factory()->for($customer)->create(['stage' => DealStage::Won, 'lost_reason' => null]);
        Deal::factory()->for($customer)->create(['stage' => DealStage::Lead, 'lost_reason' => null]);

        $this->getJson('/api/deals?stage=won')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stage', 'won');
    }

    public function test_an_invalid_stage_filter_is_ignored(): void
    {
        Deal::factory()->count(2)->create(['lost_reason' => null, 'stage' => DealStage::Lead]);

        $this->getJson('/api/deals?stage=banana')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_includes_the_customer(): void
    {
        $deal = Deal::factory()->create();

        $this->getJson("/api/deals/{$deal->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.id', $deal->customer_id);
    }

    public function test_store_creates_a_deal_and_keeps_money_exact(): void
    {
        $customer = Customer::factory()->create();

        $this->postJson('/api/deals', $this->payload($customer))
            ->assertCreated()
            ->assertJsonPath('data.value', '1500.50')
            ->assertJsonPath('data.stage', 'lead');

        $this->assertDatabaseHas('deals', ['customer_id' => $customer->id, 'value' => '1500.50']);
    }

    public function test_a_lost_deal_requires_a_reason(): void
    {
        $customer = Customer::factory()->create();

        $this->postJson('/api/deals', $this->payload($customer, ['stage' => 'lost']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lost_reason']);

        $this->postJson('/api/deals', $this->payload($customer, ['stage' => 'lost', 'lost_reason' => 'Budget']))
            ->assertCreated();
    }

    public function test_store_validates_input(): void
    {
        $this->postJson('/api/deals', [
            'customer_id' => 9999,
            'title' => '',
            'value' => -5,
            'stage' => 'banana',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'title', 'value', 'stage']);
    }
}