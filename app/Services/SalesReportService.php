<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\Deal;
use Illuminate\Database\Eloquent\Builder;

class SalesReportService
{
    public function stats(): array
    {
        $stageRows = Deal::query()
            ->selectRaw('stage, COUNT(*) as count, CAST(COALESCE(SUM(value), 0) AS DECIMAL(14,2)) as total')
            ->groupBy('stage')
            ->get()
            ->keyBy(fn ($row) => $row->stage->value);

        $byStage = collect(DealStage::cases())->mapWithKeys(fn (DealStage $stage) => [
            $stage->value => [
                'count' => (int) ($stageRows->get($stage->value)?->count ?? 0),
                'total' => (string) ($stageRows->get($stage->value)?->total ?? '0.00'),
            ],
        ]);

        $won = $byStage[DealStage::Won->value];
        $lost = $byStage[DealStage::Lost->value];
        $closed = $won['count'] + $lost['count'];
        $today = today();

        return [
            'as_of' => $today->toDateString(),
            'total_deals' => $byStage->sum('count'),
            'pipeline' => $this->aggregate(Deal::open()),
            'won' => $won,
            'lost' => $lost,
            'win_rate_percent' => $closed > 0 ? round($won['count'] / $closed * 100, 1) : null,
            'by_stage' => $byStage,
            'closing_next_30_days' => $this->aggregate(
                Deal::open()->whereBetween('expected_close_date', [$today, $today->copy()->addDays(30)])
            ),
            'overdue_open_deals' => $this->aggregate(
                Deal::open()->where('expected_close_date', '<', $today)
            ),
            'top_customers' => $this->topCustomers(),
            'lost_reasons' => $this->lostReasons(),
        ];
    }

    private function aggregate(Builder $query): array
    {
        $row = $query
            ->selectRaw('COUNT(*) as count, CAST(COALESCE(SUM(value), 0) AS DECIMAL(14,2)) as total')
            ->first();

        return ['count' => (int) $row->count, 'total' => (string) $row->total];
    }

    private function topCustomers(): array
    {
        return Customer::query()
            ->join('deals', 'deals.customer_id', '=', 'customers.id')
            ->selectRaw('customers.id, customers.name, customers.company, COUNT(deals.id) as deals_count, CAST(SUM(deals.value) AS DECIMAL(14,2)) as total_value')
            ->groupBy('customers.id', 'customers.name', 'customers.company')
            ->orderByDesc('total_value')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'company' => $c->company,
                'deals_count' => (int) $c->deals_count,
                'total_value' => (string) $c->total_value,
            ])
            ->all();
    }

    private function lostReasons(): array
    {
        return Deal::query()
            ->where('stage', DealStage::Lost->value)
            ->whereNotNull('lost_reason')
            ->selectRaw('lost_reason as reason, COUNT(*) as count')
            ->groupBy('lost_reason')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['reason' => $r->reason, 'count' => (int) $r->count])
            ->all();
    }
}