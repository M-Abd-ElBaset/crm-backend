<?php

namespace App\Services\Ai;

use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\Deal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DealTools
{
    /** Tool schemas sent to the model. */
    public function definitions(): array
    {
        $limit = [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'description' => 'How many rows to return, 1 to 10. Default 5.'],
            ],
        ];

        return [
            [
                'name' => 'top_open_deals',
                'description' => 'Highest-value open deals (not won or lost), sorted by value, highest first.',
                'parameters' => $limit,
            ],
            [
                'name' => 'deals_closing_in_month',
                'description' => 'Open deals whose expected close date falls in a given month. Omit month for the current month.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'month' => ['type' => 'string', 'description' => 'Month as YYYY-MM, for example 2026-12.'],
                    ],
                ],
            ],
            [
                'name' => 'total_value_by_stage',
                'description' => 'Number of deals and their total value for one sales stage.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'stage' => [
                            'type' => 'string',
                            'enum' => array_map(fn (DealStage $s) => $s->value, DealStage::cases()),
                        ],
                    ],
                    'required' => ['stage'],
                ],
            ],
            [
                'name' => 'customers_by_active_deals',
                'description' => 'Customers ranked by their number of open deals, with the total value of those deals.',
                'parameters' => $limit,
            ],
            [
                'name' => 'lost_deal_reasons',
                'description' => 'Lost deals with their recorded loss reasons, and a count per reason.',
            ],
        ];
    }

    /** Runs one tool. Unknown tools and bad arguments return an error array, never an exception. */
    public function execute(string $name, array $args): array
    {
        return match ($name) {
            'top_open_deals' => $this->topOpenDeals($this->limit($args)),
            'deals_closing_in_month' => $this->closingInMonth($args['month'] ?? null),
            'total_value_by_stage' => $this->totalByStage((string) ($args['stage'] ?? '')),
            'customers_by_active_deals' => $this->customersByActiveDeals($this->limit($args)),
            'lost_deal_reasons' => $this->lostDealReasons(),
            default => ['error' => 'Unknown tool.'],
        };
    }

    private function limit(array $args): int
    {
        return max(1, min(10, (int) ($args['limit'] ?? 5)));
    }

    private function topOpenDeals(int $limit): array
    {
        $deals = Deal::open()
            ->with('customer:id,name,company')
            ->orderByDesc('value')
            ->limit($limit)
            ->get();

        return ['deals' => $deals->map(fn (Deal $d) => $this->row($d))->all()];
    }

    private function closingInMonth(?string $month): array
    {
        $month ??= now()->format('Y-m');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return ['error' => 'Invalid month. Use the format YYYY-MM.'];
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
        $range = [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];

        $query = fn () => Deal::open()->whereBetween('expected_close_date', $range);

        return [
            'month' => $month,
            'summary' => $this->aggregate($query()),
            'deals' => $query()->with('customer:id,name,company')
                ->orderBy('expected_close_date')->limit(20)->get()
                ->map(fn (Deal $d) => $this->row($d))->all(),
        ];
    }

    private function totalByStage(string $stage): array
    {
        $enum = DealStage::tryFrom($stage);

        if (! $enum) {
            return ['error' => 'Invalid stage.'];
        }

        return ['stage' => $enum->value] + $this->aggregate(Deal::where('stage', $enum->value));
    }

    private function customersByActiveDeals(int $limit): array
    {
        $rows = Customer::query()
            ->join('deals', 'deals.customer_id', '=', 'customers.id')
            ->whereIn('deals.stage', DealStage::openValues())
            ->selectRaw('customers.id, customers.name, customers.company, COUNT(deals.id) as active_deals, CAST(SUM(deals.value) AS DECIMAL(14,2)) as active_value')
            ->groupBy('customers.id', 'customers.name', 'customers.company')
            ->orderByDesc('active_deals')
            ->orderByDesc('active_value')
            ->limit($limit)
            ->get();

        return ['customers' => $rows->map(fn ($c) => [
            'name' => $c->name,
            'company' => $c->company,
            'active_deals' => (int) $c->active_deals,
            'active_value' => (string) $c->active_value,
        ])->all()];
    }

    private function lostDealReasons(): array
    {
        $deals = Deal::where('stage', DealStage::Lost->value)
            ->with('customer:id,name,company')
            ->orderByDesc('value')
            ->get();

        return [
            'count' => $deals->count(),
            'by_reason' => $deals->groupBy(fn (Deal $d) => $d->lost_reason ?: 'No reason recorded')
                ->map->count()->all(),
            'deals' => $deals->take(20)->map(fn (Deal $d) => $this->row($d) + [
                'lost_reason' => $d->lost_reason ?: 'No reason recorded',
            ])->all(),
        ];
    }

    private function aggregate(Builder $query): array
    {
        $row = $query
            ->selectRaw('COUNT(*) as count, CAST(COALESCE(SUM(value), 0) AS DECIMAL(14,2)) as total')
            ->first();

        return ['count' => (int) $row->count, 'total' => (string) $row->total];
    }

    /** Only the fields the AI needs. No email or phone. */
    private function row(Deal $d): array
    {
        return [
            'title' => $d->title,
            'customer' => $d->customer?->name,
            'company' => $d->customer?->company,
            'value' => $d->value,
            'stage' => $d->stage->value,
            'expected_close_date' => $d->expected_close_date?->toDateString(),
        ];
    }
}