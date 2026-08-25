<?php

namespace App\Services\Stock;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "What is running out here, and how much should I ask for?"
 *
 * The suggested quantity is par level minus what is on the shelf, so most of
 * the time a branch user opens the screen, glances at the numbers, and sends.
 * That is the difference between a 30-second job and a five-minute one.
 */
class LowStockService
{
    /**
     * Every item a branch can ask for, with what it has and what it should ask
     * for. One query - this screen must open instantly on a bad connection.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function itemsForBranch(int $branchId): Collection
    {
        $rows = DB::table('items')
            ->join('categories', 'categories.id', '=', 'items.category_id')
            ->leftJoin('stock_balances', function ($join) use ($branchId) {
                $join->on('stock_balances.item_id', '=', 'items.id')
                    ->where('stock_balances.branch_id', '=', $branchId);
            })
            ->leftJoin('branch_item_settings', function ($join) use ($branchId) {
                $join->on('branch_item_settings.item_id', '=', 'items.id')
                    ->where('branch_item_settings.branch_id', '=', $branchId);
            })
            ->whereNull('items.deleted_at')
            ->where('items.is_active', true)
            ->where('categories.is_active', true)
            ->orderBy('categories.sort_order')
            ->orderBy('items.sort_order')
            ->orderBy('items.name')
            ->select([
                'items.id',
                'items.name',
                'items.category_id',
                'items.base_unit',
                'items.order_unit',
                'items.conversion_factor',
                'items.step_x100',
                'categories.name as category_name',
                DB::raw('COALESCE(stock_balances.qty_on_hand, 0) as qty_on_hand'),
                DB::raw('COALESCE(branch_item_settings.par_level, 0) as par_level'),
                DB::raw('COALESCE(branch_item_settings.reorder_level, 0) as reorder_level'),
            ])
            ->get();

        return $rows->map(fn ($row) => $this->present($row));
    }

    /**
     * Only what is below its reorder level, worst first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function runningLow(int $branchId, int $limit = 20): Collection
    {
        return $this->itemsForBranch($branchId)
            ->filter(fn (array $item) => $item['is_low'])
            ->sortBy('fill_ratio')
            ->take($limit)
            ->values();
    }

    /**
     * How many items are low across every branch - the admin's dashboard tile.
     */
    public function lowCountEverywhere(): int
    {
        return DB::table('branch_item_settings')
            ->join('stock_balances', function ($join) {
                $join->on('stock_balances.item_id', '=', 'branch_item_settings.item_id')
                    ->on('stock_balances.branch_id', '=', 'branch_item_settings.branch_id');
            })
            ->join('items', 'items.id', '=', 'branch_item_settings.item_id')
            ->join('branches', 'branches.id', '=', 'branch_item_settings.branch_id')
            ->whereNull('items.deleted_at')
            ->where('items.is_active', true)
            ->where('branches.is_active', true)
            ->where('branch_item_settings.reorder_level', '>', 0)
            ->whereColumn('stock_balances.qty_on_hand', '<=', 'branch_item_settings.reorder_level')
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        // A lightweight stand-in so Quantity can format without another query.
        $item = new Item([
            'name' => $row->name,
            'base_unit' => $row->base_unit,
            'order_unit' => $row->order_unit,
            'conversion_factor' => (int) $row->conversion_factor,
            'step_x100' => (int) $row->step_x100,
        ]);
        $item->id = $row->id;

        $onHand = $item->quantity((int) $row->qty_on_hand);
        $par = (int) $row->par_level;
        $reorder = (int) $row->reorder_level;

        // Top the shelf back up. Never suggest a negative.
        $suggestedBase = max(0, $par - (int) $row->qty_on_hand);

        // Round to a whole number of steps. "36.04 kg" makes a person stop and
        // think; "36 kg" is what they would actually say out loud, and this
        // screen is supposed to take thirty seconds.
        $stepBase = max(1, (int) round($item->stepSize() * $item->conversion_factor));

        if ($suggestedBase > 0) {
            $suggestedBase = max($stepBase, (int) (round($suggestedBase / $stepBase) * $stepBase));
        }

        return [
            'id' => (int) $row->id,
            'name' => $row->name,
            'category_id' => (int) $row->category_id,
            'category' => $row->category_name,
            'unit' => $item->quantity(0)->unitLabel(),
            'step' => $item->stepSize(),
            'decimals' => $item->decimals(),
            'on_hand' => $onHand->toOrderUnit(),
            'on_hand_text' => $onHand->forDisplay(),
            'suggested' => $item->quantity($suggestedBase)->toOrderUnit(),
            'is_low' => $reorder > 0 && (int) $row->qty_on_hand <= $reorder,
            // How full the shelf is, for sorting the worst to the top.
            'fill_ratio' => $par > 0 ? round(((int) $row->qty_on_hand) / $par, 4) : 1,
        ];
    }
}
