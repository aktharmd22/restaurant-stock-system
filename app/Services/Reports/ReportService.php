<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\Item;
use App\Services\Stock\StockViewService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Every report is built the same way: a title, a list of columns, and rows.
 *
 * That one shape is what lets the screen, the Excel file and the PDF all come
 * from a single definition instead of three that drift apart.
 */
class ReportService
{
    /**
     * @return array<int, array{key: string, title: string, hint: string, needs_branch: bool}>
     */
    public function definitions(): array
    {
        return [
            [
                'key' => 'stock_on_hand',
                'title' => 'Stock on hand',
                'hint' => 'What is on the shelf right now, and what it is worth.',
                'needs_branch' => true,
            ],
            [
                'key' => 'request_variance',
                'title' => 'Asked, approved, sent, arrived',
                'hint' => 'The gaps between what was wanted and what turned up.',
                'needs_branch' => false,
            ],
            [
                'key' => 'consumption',
                'title' => 'Used per branch',
                'hint' => 'What each branch got through.',
                'needs_branch' => false,
            ],
            [
                'key' => 'wastage',
                'title' => 'Thrown away',
                'hint' => 'What was wasted, why, and roughly what it cost.',
                'needs_branch' => false,
            ],
            [
                'key' => 'cost_per_branch',
                'title' => 'Cost per branch',
                'hint' => 'What each branch received, valued at what it was bought for.',
                'needs_branch' => false,
            ],
            [
                'key' => 'price_trend',
                'title' => 'What prices are doing',
                'hint' => 'What each item has cost over time.',
                'needs_branch' => false,
            ],
        ];
    }

    /**
     * @param  array{from?: ?string, to?: ?string, branch?: ?int}  $filters
     * @return array<string, mixed>
     */
    public function run(string $key, array $filters = []): array
    {
        $definition = collect($this->definitions())->firstWhere('key', $key);

        if (! $definition) {
            throw new InvalidArgumentException("There is no report called {$key}.");
        }

        // This month, unless asked otherwise.
        $from = CarbonImmutable::parse($filters['from'] ?? CarbonImmutable::now()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? CarbonImmutable::now())->endOfDay();
        $branchId = $filters['branch'] ?? null;

        $result = match ($key) {
            'stock_on_hand' => $this->stockOnHand($branchId),
            'request_variance' => $this->requestVariance($from, $to, $branchId),
            'consumption' => $this->consumption($from, $to, $branchId),
            'wastage' => $this->wastage($from, $to, $branchId),
            'cost_per_branch' => $this->costPerBranch($from, $to, $branchId),
            'price_trend' => $this->priceTrend($from, $to),
        };

        return [
            'key' => $key,
            'title' => $definition['title'],
            'hint' => $definition['hint'],
            'needs_branch' => $definition['needs_branch'],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->format('j M Y').' to '.$to->format('j M Y'),
            ],
            'branch' => $branchId ? Branch::find($branchId)?->name : 'Every branch',
            ...$result,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The reports
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function stockOnHand(?int $branchId): array
    {
        $branch = $branchId ? Branch::find($branchId) : Branch::main();
        $rows = app(StockViewService::class)->forBranch($branch->id);

        return [
            'branch' => $branch->name,
            'columns' => [
                ['key' => 'name', 'label' => 'Item'],
                ['key' => 'category', 'label' => 'Group'],
                ['key' => 'on_hand_text', 'label' => 'On hand', 'align' => 'right'],
                ['key' => 'available_text', 'label' => 'Free', 'align' => 'right'],
                ['key' => 'par_text', 'label' => 'Full shelf', 'align' => 'right'],
                ['key' => 'value', 'label' => 'Worth', 'align' => 'right', 'type' => 'money'],
            ],
            'rows' => $rows->map(fn (array $row) => [
                'name' => $row['name'],
                'category' => $row['category'],
                'on_hand_text' => $row['on_hand_text'],
                'available_text' => $row['available_text'],
                'par_text' => $row['par_text'] ?? '—',
                'value' => $row['value'],
                'is_low' => $row['is_low'],
            ])->values(),
            'totals' => [
                'Items' => $rows->count(),
                'Running low' => $rows->where('is_low', true)->count(),
                'Total worth' => $this->money($rows->sum('value')),
            ],
        ];
    }

    /**
     * The heart of the reporting: where the promises and the reality diverge.
     *
     * @return array<string, mixed>
     */
    private function requestVariance(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        $rows = DB::table('request_lines as rl')
            ->join('requests as r', 'r.id', '=', 'rl.request_id')
            ->join('items', 'items.id', '=', 'rl.item_id')
            ->whereNull('r.deleted_at')
            ->whereBetween('r.submitted_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('r.from_branch_id', $branchId))
            ->groupBy('items.id', 'items.name', 'items.order_unit', 'items.base_unit', 'items.conversion_factor', 'items.step_x100')
            ->orderBy('items.name')
            ->select([
                'items.id', 'items.name', 'items.order_unit', 'items.base_unit',
                'items.conversion_factor', 'items.step_x100',
                DB::raw('SUM(rl.qty_requested) as asked'),
                DB::raw('SUM(COALESCE(rl.qty_approved, 0)) as approved'),
                DB::raw('SUM(COALESCE(rl.qty_sent, 0)) as sent'),
                DB::raw('SUM(COALESCE(rl.qty_received, 0)) as arrived'),
            ])
            ->get()
            ->map(function ($row) {
                $item = $this->itemFrom($row);
                $asked = (int) $row->asked;
                $arrived = (int) $row->arrived;

                return [
                    'name' => $row->name,
                    'asked' => $item->quantity($asked)->forDisplay(),
                    'approved' => $item->quantity((int) $row->approved)->forDisplay(),
                    'sent' => $item->quantity((int) $row->sent)->forDisplay(),
                    'arrived' => $item->quantity($arrived)->forDisplay(),
                    // The one number that says whether branches are getting
                    // what they ask for.
                    'filled' => $asked > 0 ? round($arrived / $asked * 100).'%' : '—',
                    'shortfall' => $item->quantity(max(0, $asked - $arrived))->forDisplay(),
                ];
            });

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Item'],
                ['key' => 'asked', 'label' => 'Asked', 'align' => 'right'],
                ['key' => 'approved', 'label' => 'Approved', 'align' => 'right'],
                ['key' => 'sent', 'label' => 'Sent', 'align' => 'right'],
                ['key' => 'arrived', 'label' => 'Arrived', 'align' => 'right'],
                ['key' => 'shortfall', 'label' => 'Short by', 'align' => 'right'],
                ['key' => 'filled', 'label' => 'Filled', 'align' => 'right'],
            ],
            'rows' => $rows,
            'totals' => ['Items asked for' => $rows->count()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consumption(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        $rows = $this->movementTotals('consumption', $from, $to, $branchId);

        return [
            'columns' => [
                ['key' => 'branch', 'label' => 'Branch'],
                ['key' => 'name', 'label' => 'Item'],
                ['key' => 'qty', 'label' => 'Used', 'align' => 'right'],
                ['key' => 'value', 'label' => 'Roughly worth', 'align' => 'right', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => ['Total' => $this->money($rows->sum('value'))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wastage(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        $rows = DB::table('wastage as w')
            ->join('items', 'items.id', '=', 'w.item_id')
            ->join('branches', 'branches.id', '=', 'w.branch_id')
            ->leftJoin('stock_balances as sb', function ($join) {
                $join->on('sb.item_id', '=', 'w.item_id')->on('sb.branch_id', '=', 'w.branch_id');
            })
            ->whereBetween('w.created_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('w.branch_id', $branchId))
            ->groupBy('branches.name', 'items.id', 'items.name', 'items.order_unit', 'items.base_unit', 'items.conversion_factor', 'items.step_x100', 'w.reason')
            ->orderByDesc(DB::raw('SUM(w.qty)'))
            ->select([
                'branches.name as branch', 'items.id', 'items.name', 'items.order_unit',
                'items.base_unit', 'items.conversion_factor', 'items.step_x100', 'w.reason',
                DB::raw('SUM(w.qty) as total'),
                DB::raw('SUM(w.qty * COALESCE(sb.avg_cost, 0)) as value'),
            ])
            ->get()
            ->map(function ($row) {
                $item = $this->itemFrom($row);

                return [
                    'branch' => $row->branch,
                    'name' => $row->name,
                    'reason' => \App\Enums\WastageReason::from($row->reason)->label(),
                    'qty' => $item->quantity((int) $row->total)->forDisplay(),
                    'value' => round((float) $row->value, 2),
                ];
            });

        // What was received in the same period, so waste can be a percentage
        // rather than a number nobody can judge.
        $receivedBase = (int) DB::table('stock_ledger')
            ->where('movement_type', 'transfer_in')
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->sum('qty_delta');

        $wastedBase = (int) DB::table('wastage')
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->sum('qty');

        return [
            'columns' => [
                ['key' => 'branch', 'label' => 'Branch'],
                ['key' => 'name', 'label' => 'Item'],
                ['key' => 'reason', 'label' => 'Why'],
                ['key' => 'qty', 'label' => 'Thrown away', 'align' => 'right'],
                ['key' => 'value', 'label' => 'Roughly worth', 'align' => 'right', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [
                'Total worth' => $this->money($rows->sum('value')),
                'Waste as share of what arrived' => $receivedBase > 0
                    ? round($wastedBase / $receivedBase * 100, 1).'%'
                    : '—',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function costPerBranch(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        $rows = DB::table('stock_ledger as sl')
            ->join('branches', 'branches.id', '=', 'sl.branch_id')
            ->whereIn('sl.movement_type', ['transfer_in', 'purchase'])
            ->whereBetween('sl.created_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('sl.branch_id', $branchId))
            ->groupBy('branches.id', 'branches.name', 'branches.type')
            ->orderBy('branches.name')
            ->select([
                'branches.name as branch',
                'branches.type',
                DB::raw('SUM(CASE WHEN sl.movement_type = \'transfer_in\' THEN sl.qty_delta * COALESCE(sl.unit_cost, 0) ELSE 0 END) as transferred'),
                DB::raw('SUM(CASE WHEN sl.movement_type = \'purchase\' THEN sl.qty_delta * COALESCE(sl.unit_cost, 0) ELSE 0 END) as bought'),
                DB::raw('COUNT(*) as movements'),
            ])
            ->get()
            ->map(fn ($row) => [
                'branch' => $row->branch.($row->type === 'main' ? ' (main store)' : ''),
                'transferred' => round((float) $row->transferred, 2),
                'bought' => round((float) $row->bought, 2),
                'total' => round((float) $row->transferred + (float) $row->bought, 2),
                'movements' => (int) $row->movements,
            ]);

        return [
            'columns' => [
                ['key' => 'branch', 'label' => 'Branch'],
                ['key' => 'transferred', 'label' => 'From main store', 'align' => 'right', 'type' => 'money'],
                ['key' => 'bought', 'label' => 'Bought direct', 'align' => 'right', 'type' => 'money'],
                ['key' => 'total', 'label' => 'Total', 'align' => 'right', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => ['Everything' => $this->money($rows->sum('total'))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceTrend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('stock_ledger as sl')
            ->join('items', 'items.id', '=', 'sl.item_id')
            ->where('sl.movement_type', 'purchase')
            ->whereNotNull('sl.unit_cost')
            ->where('sl.unit_cost', '>', 0)
            ->whereBetween('sl.created_at', [$from, $to])
            ->groupBy('items.id', 'items.name', 'items.order_unit', 'items.base_unit', 'items.conversion_factor', 'items.step_x100')
            ->orderBy('items.name')
            ->select([
                'items.id', 'items.name', 'items.order_unit', 'items.base_unit',
                'items.conversion_factor', 'items.step_x100',
                DB::raw('MIN(sl.unit_cost) as lowest'),
                DB::raw('MAX(sl.unit_cost) as highest'),
                DB::raw('AVG(sl.unit_cost) as average'),
                DB::raw('COUNT(*) as times'),
            ])
            ->get()
            ->map(function ($row) {
                $factor = max(1, (int) $row->conversion_factor);
                $lowest = (float) $row->lowest * $factor;
                $highest = (float) $row->highest * $factor;

                return [
                    'name' => $row->name,
                    'unit' => $row->order_unit,
                    'lowest' => round($lowest, 2),
                    'average' => round((float) $row->average * $factor, 2),
                    'highest' => round($highest, 2),
                    // The number worth arguing with a supplier about.
                    'swing' => $lowest > 0 ? round(($highest - $lowest) / $lowest * 100).'%' : '—',
                    'times' => (int) $row->times,
                ];
            });

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Item'],
                ['key' => 'unit', 'label' => 'Per'],
                ['key' => 'lowest', 'label' => 'Cheapest', 'align' => 'right', 'type' => 'money'],
                ['key' => 'average', 'label' => 'Usually', 'align' => 'right', 'type' => 'money'],
                ['key' => 'highest', 'label' => 'Dearest', 'align' => 'right', 'type' => 'money'],
                ['key' => 'swing', 'label' => 'Swing', 'align' => 'right'],
                ['key' => 'times', 'label' => 'Times bought', 'align' => 'right'],
            ],
            'rows' => $rows,
            'totals' => ['Items bought' => $rows->count()],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function movementTotals(string $type, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Collection
    {
        return DB::table('stock_ledger as sl')
            ->join('items', 'items.id', '=', 'sl.item_id')
            ->join('branches', 'branches.id', '=', 'sl.branch_id')
            ->leftJoin('stock_balances as sb', function ($join) {
                $join->on('sb.item_id', '=', 'sl.item_id')->on('sb.branch_id', '=', 'sl.branch_id');
            })
            ->where('sl.movement_type', $type)
            ->whereBetween('sl.created_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('sl.branch_id', $branchId))
            ->groupBy('branches.name', 'items.id', 'items.name', 'items.order_unit', 'items.base_unit', 'items.conversion_factor', 'items.step_x100')
            ->orderBy('branches.name')
            ->orderBy('items.name')
            ->select([
                'branches.name as branch', 'items.id', 'items.name', 'items.order_unit',
                'items.base_unit', 'items.conversion_factor', 'items.step_x100',
                DB::raw('SUM(ABS(sl.qty_delta)) as total'),
                DB::raw('SUM(ABS(sl.qty_delta) * COALESCE(sb.avg_cost, 0)) as value'),
            ])
            ->get()
            ->map(function ($row) {
                $item = $this->itemFrom($row);

                return [
                    'branch' => $row->branch,
                    'name' => $row->name,
                    'qty' => $item->quantity((int) $row->total)->forDisplay(),
                    'value' => round((float) $row->value, 2),
                ];
            });
    }

    /** A stand-in item, so Quantity can format without another query. */
    private function itemFrom(object $row): Item
    {
        $item = new Item([
            'name' => $row->name,
            'base_unit' => $row->base_unit,
            'order_unit' => $row->order_unit,
            'conversion_factor' => (int) $row->conversion_factor,
            'step_x100' => (int) $row->step_x100,
        ]);
        $item->id = $row->id ?? 0;

        return $item;
    }

    private function money(float $amount): string
    {
        return setting('currency_symbol').number_format($amount, 2);
    }
}
