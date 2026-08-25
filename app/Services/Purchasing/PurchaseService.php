<?php

namespace App\Services\Purchasing;

use App\Exceptions\StockException;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\SequenceService;
use App\Services\Stock\StockLedgerService;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buying from suppliers.
 *
 * Ordering moves no stock - a purchase order is a promise, not a delivery.
 * Stock appears only when goods are actually received against the order, and
 * it is valued at the price on that order line.
 */
class PurchaseService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly SequenceService $sequences,
    ) {
    }

    /**
     * @param  array<int, array{item_id: int, qty: float|int|string, unit_price: float|int|string}>  $lines
     *                                                                                                      Quantities in ORDER units, price per ORDER unit - which is how a
     *                                                                                                      supplier quotes it. Both are converted before storing.
     */
    public function createOrder(
        int $supplierId,
        int $branchId,
        User $by,
        array $lines,
        ?string $expectedDate = null,
        ?string $note = null,
    ): PurchaseOrder {
        $items = Item::active()->whereIn('id', collect($lines)->pluck('item_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($supplierId, $branchId, $by, $lines, $expectedDate, $note, $items) {
            $order = PurchaseOrder::create([
                'po_number' => $this->sequences->purchaseOrderNumber(),
                'supplier_id' => $supplierId,
                'branch_id' => $branchId,
                'status' => 'ordered',
                'expected_date' => $expectedDate,
                'note' => $note,
                'created_by' => $by->id,
                'total_amount' => 0,
            ]);

            $total = 0.0;

            foreach ($lines as $line) {
                $item = $items->get((int) ($line['item_id'] ?? 0));

                if (! $item) {
                    continue;
                }

                $quantity = Quantity::fromOrderUnit($line['qty'] ?? 0, $item);

                if (! $quantity->isPositive()) {
                    continue;
                }

                // A supplier quotes per kilo; the ledger values per gram.
                $pricePerBaseUnit = ((float) ($line['unit_price'] ?? 0)) / max(1, $item->conversion_factor);

                PurchaseOrderLine::create([
                    'purchase_order_id' => $order->id,
                    'item_id' => $item->id,
                    'qty_ordered' => $quantity->baseUnits,
                    'qty_received' => 0,
                    'unit_price' => round($pricePerBaseUnit, 4),
                ]);

                $total += $quantity->baseUnits * $pricePerBaseUnit;
            }

            if ($order->lines()->count() === 0) {
                throw new StockException('Add at least one item to the order.');
            }

            $order->update(['total_amount' => round($total, 2)]);

            return $order->fresh(['lines.item', 'supplier']);
        });
    }

    /**
     * Goods turned up. This is the only point where a purchase order puts
     * anything on a shelf.
     *
     * @param  array<int, float|int|string>  $received  Keyed by line id, in ORDER units.
     */
    public function receiveGoods(PurchaseOrder $order, User $by, array $received): PurchaseOrder
    {
        if (in_array($order->status, ['received', 'cancelled'], true)) {
            throw new StockException('This order is already closed.');
        }

        return DB::transaction(function () use ($order, $by, $received) {
            $lines = $order->lines()->with('item')->orderBy('item_id')->get();
            $anything = false;

            foreach ($lines as $line) {
                if (! array_key_exists($line->id, $received)) {
                    continue;
                }

                $quantity = Quantity::fromOrderUnit($received[$line->id], $line->item);

                if (! $quantity->isPositive()) {
                    continue;
                }

                $outstanding = $line->outstandingBase();

                if ($quantity->baseUnits > $outstanding) {
                    throw new StockException(
                        "More {$line->item->name} arrived than was ordered "
                        .'('.$line->item->quantity($outstanding)->forDisplay().' still due).'
                    );
                }

                // The ledger references this delivery, not the order line: a
                // line can be delivered several times, and referencing the line
                // would make the second delivery look like a duplicate of the
                // first and quietly drop the stock.
                $receipt = GoodsReceipt::create([
                    'purchase_order_line_id' => $line->id,
                    'qty' => $quantity->baseUnits,
                    'received_by' => $by->id,
                ]);

                $this->ledger->purchase(
                    $order->branch_id,
                    $quantity,
                    $receipt,
                    (float) $line->unit_price,
                    $by,
                );

                $line->increment('qty_received', $quantity->baseUnits);
                $anything = true;
            }

            if (! $anything) {
                throw new StockException('Enter what actually arrived.');
            }

            $order->update(['status' => $this->statusFor($order->lines()->get())]);

            return $order->fresh(['lines.item', 'supplier']);
        });
    }

    /**
     * What to buy next, totalled across every branch.
     *
     * A branch shortfall becomes a main-store shortfall the moment it is
     * requested, so the main store needs enough to cover everyone plus its own
     * shelf - this is what stops the "we ran out again" conversation.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function suggestions(int $mainBranchId): Collection
    {
        $rows = DB::table('items')
            ->join('categories', 'categories.id', '=', 'items.category_id')
            ->leftJoin('stock_balances as main_balance', function ($join) use ($mainBranchId) {
                $join->on('main_balance.item_id', '=', 'items.id')
                    ->where('main_balance.branch_id', '=', $mainBranchId);
            })
            ->whereNull('items.deleted_at')
            ->where('items.is_active', true)
            ->select([
                'items.id',
                'items.name',
                'items.order_unit',
                'items.base_unit',
                'items.conversion_factor',
                'items.step_x100',
                'categories.name as category_name',
                DB::raw('COALESCE(main_balance.qty_on_hand, 0) - COALESCE(main_balance.qty_reserved, 0) as free_at_main'),
                DB::raw('(SELECT COALESCE(SUM(GREATEST(bis.par_level - COALESCE(b.qty_on_hand, 0), 0)), 0)
                          FROM branch_item_settings bis
                          JOIN branches br ON br.id = bis.branch_id AND br.type = \'sub\' AND br.is_active = 1
                          LEFT JOIN stock_balances b ON b.item_id = bis.item_id AND b.branch_id = bis.branch_id
                          WHERE bis.item_id = items.id) as branch_need'),
                DB::raw('(SELECT COALESCE(bis2.par_level, 0) FROM branch_item_settings bis2
                          WHERE bis2.item_id = items.id AND bis2.branch_id = '.(int) $mainBranchId.') as main_par'),
            ])
            ->orderBy('categories.sort_order')
            ->orderBy('items.name')
            ->get();

        return $rows
            ->map(function ($row) {
                $item = new Item([
                    'name' => $row->name,
                    'base_unit' => $row->base_unit,
                    'order_unit' => $row->order_unit,
                    'conversion_factor' => (int) $row->conversion_factor,
                    'step_x100' => (int) $row->step_x100,
                ]);
                $item->id = $row->id;

                $need = (int) $row->branch_need + (int) $row->main_par;
                $shortfall = max(0, $need - (int) $row->free_at_main);

                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'category' => $row->category_name,
                    'unit' => $item->quantity(0)->unitLabel(),
                    'step' => $item->stepSize(),
                    'decimals' => $item->decimals(),
                    'free_at_main_text' => $item->quantity((int) $row->free_at_main)->forDisplay(),
                    'branches_need_text' => $item->quantity((int) $row->branch_need)->forDisplay(),
                    'suggested' => $item->quantity($shortfall)->toOrderUnit(),
                    'suggested_text' => $item->quantity($shortfall)->forDisplay(),
                    'is_needed' => $shortfall > 0,
                ];
            })
            ->filter(fn (array $row) => $row['is_needed'])
            ->values();
    }

    /**
     * @param  Collection<int, PurchaseOrderLine>  $lines
     */
    private function statusFor(Collection $lines): string
    {
        $complete = $lines->every(fn (PurchaseOrderLine $line) => $line->qty_received >= $line->qty_ordered);
        $started = $lines->contains(fn (PurchaseOrderLine $line) => $line->qty_received > 0);

        return match (true) {
            $complete => 'received',
            $started => 'part_received',
            default => 'ordered',
        };
    }
}
