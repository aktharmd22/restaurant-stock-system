<?php

namespace App\Services\Stock;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reading stock, as opposed to moving it.
 *
 * One query builds the whole picture for a branch: what is on the shelf, what
 * is already promised to someone else, what is free, and - for anything that
 * goes off - roughly when it needs using.
 */
class StockViewService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forBranch(int $branchId, ?string $search = null, ?int $categoryId = null): Collection
    {
        $lastIn = "(SELECT MAX(sl.created_at) FROM stock_ledger sl
                    WHERE sl.branch_id = ? AND sl.item_id = items.id
                    AND sl.movement_type IN ('purchase', 'transfer_in'))";

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
            ->when($search, fn ($query, $term) => $query->where('items.name', 'like', "%{$term}%"))
            ->when($categoryId, fn ($query, $id) => $query->where('items.category_id', $id))
            ->orderBy('categories.sort_order')
            ->orderBy('items.name')
            ->select([
                'items.id',
                'items.name',
                'items.category_id',
                'items.base_unit',
                'items.order_unit',
                'items.conversion_factor',
                'items.step_x100',
                'items.is_perishable',
                'items.shelf_life_days',
                'items.storage_location',
                'categories.name as category_name',
                DB::raw('COALESCE(stock_balances.qty_on_hand, 0) as qty_on_hand'),
                DB::raw('COALESCE(stock_balances.qty_reserved, 0) as qty_reserved'),
                DB::raw('COALESCE(stock_balances.avg_cost, 0) as avg_cost'),
                DB::raw('COALESCE(branch_item_settings.par_level, 0) as par_level'),
                DB::raw('COALESCE(branch_item_settings.reorder_level, 0) as reorder_level'),
                DB::raw("{$lastIn} as last_in"),
            ])
            ->addBinding($branchId, 'select')
            ->get();

        return $rows->map(fn ($row) => $this->present($row));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        $item = new Item([
            'name' => $row->name,
            'base_unit' => $row->base_unit,
            'order_unit' => $row->order_unit,
            'conversion_factor' => (int) $row->conversion_factor,
            'step_x100' => (int) $row->step_x100,
        ]);
        $item->id = $row->id;

        $onHand = (int) $row->qty_on_hand;
        $reserved = (int) $row->qty_reserved;
        $available = $onHand - $reserved;
        $reorder = (int) $row->reorder_level;
        $par = (int) $row->par_level;

        return [
            'id' => (int) $row->id,
            'name' => $row->name,
            'category' => $row->category_name,
            'category_id' => (int) $row->category_id,
            'storage_location' => $row->storage_location,
            'unit' => $item->quantity(0)->unitLabel(),
            'step' => $item->stepSize(),
            'decimals' => $item->decimals(),

            'on_hand' => $item->quantity($onHand)->toOrderUnit(),
            'on_hand_text' => $item->quantity($onHand)->forDisplay(),
            'reserved_text' => $item->quantity($reserved)->forDisplay(),
            'available_text' => $item->quantity($available)->forDisplay(),

            'par_text' => $par > 0 ? $item->quantity($par)->forDisplay() : null,
            'is_low' => $reorder > 0 && $onHand <= $reorder,
            'is_negative' => $onHand < 0,

            // Valued at the weighted average the ledger has been keeping.
            'value' => round($onHand * (float) $row->avg_cost, 2),

            // Not batch tracking: this is the last time any of it came in,
            // plus the shelf life. Good enough to spot what needs using first.
            'use_by' => $this->useBy($row),
        ];
    }

    private function useBy(object $row): ?string
    {
        if (! $row->is_perishable || ! $row->shelf_life_days || ! $row->last_in) {
            return null;
        }

        if ((int) $row->qty_on_hand <= 0) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($row->last_in)
            ->addDays((int) $row->shelf_life_days)
            ->toDateString();
    }
}
