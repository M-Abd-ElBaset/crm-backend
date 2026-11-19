<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\Deal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_has_many_deals_and_a_deal_belongs_to_a_customer(): void
    {
        $customer = Customer::factory()->create();
        $deals = Deal::factory()->count(3)->for($customer)->create();

        $this->assertCount(3, $customer->deals);
        $this->assertTrue($deals->first()->customer->is($customer));
    }

    public function test_the_foreign_key_rejects_a_deal_for_a_missing_customer(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Deal::factory()->create(['customer_id' => 9999]);
    }

    public function test_the_open_scope_excludes_won_and_lost_deals(): void
    {
        foreach (DealStage::cases() as $stage) {
            Deal::factory()->create(['stage' => $stage, 'lost_reason' => null]);
        }

        $this->assertSame(4, Deal::open()->count());
        $this->assertFalse(Deal::open()->whereIn('stage', ['won', 'lost'])->exists());
    }

    public function test_enum_and_decimal_casts_work(): void
    {
        $deal = Deal::factory()->create(['stage' => DealStage::Won, 'value' => '10.50', 'lost_reason' => null]);
        $deal->refresh();

        $this->assertSame(DealStage::Won, $deal->stage);
        $this->assertSame('10.50', $deal->value);
        $this->assertInstanceOf(CustomerStatus::class, $deal->customer->status);
    }
}