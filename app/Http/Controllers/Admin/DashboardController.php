<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequestPresenter;
use App\Models\Branch;
use App\Models\StockRequest;
use App\Services\Stock\LowStockService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(LowStockService $lowStock): Response
    {
        $main = Branch::main();
        $now = CarbonImmutable::now();

        $spentThisMonth = $this->purchaseValue($now->startOfMonth(), $now);
        $spentLastMonth = $this->purchaseValue(
            $now->subMonth()->startOfMonth(),
            $now->subMonth()->endOfMonth(),
        );

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'waiting' => StockRequest::waiting()->count(),
                'to_send' => StockRequest::awaitingDispatch()->count(),
                'in_transit' => StockRequest::inTransit()->count(),
                'low_stock' => $lowStock->lowCountEverywhere(),
                'stock_value' => $main ? $this->stockValue($main->id) : 0,
                'spent_this_month' => $spentThisMonth,
                'spent_change' => $this->percentChange($spentLastMonth, $spentThisMonth),
            ],

            'months' => $this->monthlyMovement(),
            'statusMix' => $this->statusMix($now->startOfMonth(), $now),
            'stockSummary' => $this->stockSummary($main?->id, $now),

            // Late first, then oldest. The admin works top to bottom.
            'needsAction' => StockRequest::with('fromBranch')
                ->withCount('lines')
                ->waiting()
                ->mostUrgentFirst()
                ->limit(6)
                ->get()
                ->map(fn (StockRequest $request) => RequestPresenter::summary($request)),

            'recent' => StockRequest::with('fromBranch')
                ->withCount('lines')
                ->whereNotNull('submitted_at')
                ->latest('submitted_at')
                ->limit(8)
                ->get()
                ->map(fn (StockRequest $request) => RequestPresenter::summary($request)),

            'currency' => setting('currency_symbol'),
        ]);
    }

    /**
     * Six months of what left the store versus what was bought into it - the
     * shape of the business in one picture.
     *
     * @return array<string, mixed>
     */
    private function monthlyMovement(): array
    {
        $start = CarbonImmutable::now()->subMonths(5)->startOfMonth();

        $rows = DB::table('stock_ledger')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw("SUM(CASE WHEN movement_type = 'transfer_out' THEN ABS(qty_delta) * COALESCE(unit_cost, 0) ELSE 0 END) as sent_out")
            ->selectRaw("SUM(CASE WHEN movement_type = 'purchase' THEN qty_delta * COALESCE(unit_cost, 0) ELSE 0 END) as bought_in")
            ->where('created_at', '>=', $start)
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $labels = [];
        $sentOut = [];
        $boughtIn = [];

        for ($i = 0; $i < 6; $i++) {
            $month = $start->addMonths($i);
            $row = $rows->get($month->format('Y-m'));

            $labels[] = $month->format('M');
            $sentOut[] = (int) round((float) ($row->sent_out ?? 0));
            $boughtIn[] = (int) round((float) ($row->bought_in ?? 0));
        }

        return [
            'labels' => $labels,
            'sent_out' => $sentOut,
            'bought_in' => $boughtIn,
        ];
    }

    /**
     * Where this month's requests ended up.
     *
     * @return array<int, array{name: string, value: int, color: string}>
     */
    private function statusMix(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = StockRequest::query()
            ->whereBetween('submitted_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $palette = [
            'waiting' => '#B45309',
            'approved' => '#15803D',
            'partial' => '#C2410C',
            'sent' => '#1E3A8A',
            'received' => '#6D3BEB',
            'rejected' => '#B91C1C',
            'cancelled' => '#9CA3AF',
        ];

        $mix = [];

        foreach ($palette as $status => $color) {
            $value = (int) ($counts[$status] ?? 0);

            if ($value > 0) {
                $mix[] = [
                    'name' => RequestStatus::from($status)->label(),
                    'value' => $value,
                    'color' => $color,
                ];
            }
        }

        return $mix;
    }

    /**
     * @return array<int, array{label: string, value: string, delta: ?int, tone: string}>
     */
    private function stockSummary(?int $mainBranchId, CarbonImmutable $now): array
    {
        $currency = setting('currency_symbol');

        $wasted = (float) DB::table('wastage')
            ->join('stock_balances', function ($join) {
                $join->on('stock_balances.item_id', '=', 'wastage.item_id')
                    ->on('stock_balances.branch_id', '=', 'wastage.branch_id');
            })
            ->whereBetween('wastage.created_at', [$now->startOfMonth(), $now])
            ->sum(DB::raw('wastage.qty * COALESCE(stock_balances.avg_cost, 0)'));

        $inTransitValue = (float) DB::table('stock_ledger')
            ->where('movement_type', 'transfer_out')
            ->whereIn('reference_id', function ($query) {
                $query->select('request_lines.id')
                    ->from('request_lines')
                    ->join('requests', 'requests.id', '=', 'request_lines.request_id')
                    ->where('requests.status', RequestStatus::Sent->value);
            })
            ->where('reference_type', 'RequestLine')
            ->sum(DB::raw('ABS(qty_delta) * COALESCE(unit_cost, 0)'));

        return [
            [
                'label' => 'Items on the shelf',
                'value' => (string) DB::table('stock_balances')
                    ->when($mainBranchId, fn ($q) => $q->where('branch_id', $mainBranchId))
                    ->where('qty_on_hand', '>', 0)
                    ->count(),
                'tone' => 'blue',
            ],
            [
                'label' => 'On the way to branches',
                'value' => $currency.number_format($inTransitValue, 0),
                'tone' => 'violet',
            ],
            [
                'label' => 'Thrown away this month',
                'value' => $currency.number_format($wasted, 0),
                'tone' => 'rose',
            ],
            [
                'label' => 'Waiting on suppliers',
                'value' => (string) DB::table('purchase_orders')
                    ->whereIn('status', ['ordered', 'part_received'])
                    ->whereNull('deleted_at')
                    ->count(),
                'tone' => 'amber',
            ],
        ];
    }

    private function stockValue(int $branchId): float
    {
        return round((float) DB::table('stock_balances')
            ->where('branch_id', $branchId)
            ->sum(DB::raw('qty_on_hand * avg_cost')), 2);
    }

    private function purchaseValue(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return round((float) DB::table('stock_ledger')
            ->where('movement_type', 'purchase')
            ->whereBetween('created_at', [$from, $to])
            ->sum(DB::raw('qty_delta * COALESCE(unit_cost, 0)')), 2);
    }

    /** Whole percent, so the card can say "20% than last month". */
    private function percentChange(float $before, float $after): ?int
    {
        if ($before <= 0) {
            return null;
        }

        return (int) round((($after - $before) / $before) * 100);
    }
}
