<?php

namespace Tests\Concerns;

use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\Deal;

trait CreatesKnownDeals
{
    protected function makeDeal(
        Customer $customer,
        string $title,
        string $value,
        DealStage $stage,
        ?string $close = null,
        ?string $reason = null,
    ): Deal {
        return Deal::factory()->for($customer)->create([
            'title' => $title,
            'value' => $value,
            'stage' => $stage,
            'expected_close_date' => $close,
            'lost_reason' => $reason,
        ]);
    }

    /** Four deals with figures you can check by hand. */
    protected function createKnownDeals(): array
    {
        $a = Customer::factory()->create(['name' => 'Alpha Contact']);
        $b = Customer::factory()->create(['name' => 'Beta Contact']);

        $this->makeDeal($a, 'Open A', '100.00', DealStage::Lead);
        $this->makeDeal($a, 'Won A', '400.00', DealStage::Won);
        $this->makeDeal($b, 'Lost B', '250.50', DealStage::Lost, reason: 'Budget');
        $this->makeDeal($b, 'Open B', '50.25', DealStage::Qualified);

        return [$a, $b];
    }
}