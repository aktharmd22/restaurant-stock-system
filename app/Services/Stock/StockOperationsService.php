<?php

namespace App\Services\Stock;

use App\Enums\WastageReason;
use App\Exceptions\StockException;
use App\Models\Branch;
use App\Models\Item;
use App\Models\LocalPurchase;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\User;
use App\Models\Wastage;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * The stock movements that are not transfers: throwing something away,
 * correcting the books after a count, and emergency local buying.
 *
 * All of them go through StockLedgerService like everything else, so the
 * ledger stays the single explanation for why a number is what it is.
 */
class StockOperationsService
{
    public function __construct(private readonly StockLedgerService $ledger)
    {
    }

    /**
     * Something got thrown away. Recorded as its own row so the waste report
     * can say what and why, then taken off the shelf.
     */
    public function recordWastage(
        Branch $branch,
        Quantity $quantity,
        WastageReason $reason,
        User $by,
        ?string $note = null,
    ): Wastage {
        if (! $quantity->isPositive()) {
            throw new StockException('Enter how much was thrown away.');
        }

        return DB::transaction(function () use ($branch, $quantity, $reason, $by, $note) {
            $wastage = Wastage::create([
                'branch_id' => $branch->id,
                'item_id' => $quantity->item->id,
                'qty' => $quantity->baseUnits,
                'reason' => $reason,
                'note' => $note,
                'recorded_by' => $by->id,
            ]);

            $this->ledger->wastage($branch->id, $quantity, $wastage, $by);

            return $wastage;
        });
    }

    /**
     * Start a count. Every line remembers what the system thought at the
     * moment counting began, so the difference is meaningful even if it takes
     * an hour to walk the store.
     *
     * @param  \Illuminate\Support\Collection<int, Item>  $items
     */
    public function openCount(Branch $branch, User $by, $items): StockCount
    {
        return DB::transaction(function () use ($branch, $by, $items) {
            $count = StockCount::create([
                'branch_id' => $branch->id,
                'counted_by' => $by->id,
                'status' => 'open',
            ]);

            $balances = StockBalance::withoutBranchScope()
                ->where('branch_id', $branch->id)
                ->pluck('qty_on_hand', 'item_id');

            foreach ($items as $item) {
                $systemQty = (int) ($balances[$item->id] ?? 0);

                StockCountLine::create([
                    'stock_count_id' => $count->id,
                    'item_id' => $item->id,
                    'system_qty' => $systemQty,
                    'counted_qty' => $systemQty,
                    'difference' => 0,
                ]);
            }

            return $count->fresh(['lines']);
        });
    }

    /**
     * Apply the count. The shelf wins: whatever was counted becomes the
     * balance, and the gap is written to the ledger as a correction with a
     * reason attached. Nothing is silently overwritten.
     *
     * @param  array<int, array{counted: float|int|string, note?: ?string}>  $counted  Keyed by line id, in ORDER units.
     */
    public function applyCount(StockCount $count, array $counted, User $by, ?string $note = null): StockCount
    {
        if ($count->status !== 'open') {
            throw new StockException('This count has already been applied.');
        }

        if (blank($note)) {
            throw new StockException('Say why the numbers are different before applying the count.');
        }

        return DB::transaction(function () use ($count, $counted, $by, $note) {
            // Ascending item id keeps the lock order consistent with everything
            // else that touches several balances at once.
            $lines = $count->lines()->with('item')->orderBy('item_id')->get();

            foreach ($lines as $line) {
                if (! array_key_exists($line->id, $counted)) {
                    continue;
                }

                $countedQty = Quantity::fromOrderUnit($counted[$line->id]['counted'] ?? 0, $line->item);
                $difference = $countedQty->baseUnits - $line->system_qty;

                $line->update([
                    'counted_qty' => $countedQty->baseUnits,
                    'difference' => $difference,
                    'note' => $counted[$line->id]['note'] ?? null,
                ]);

                if ($difference !== 0) {
                    $this->ledger->adjustment(
                        $count->branch_id,
                        Quantity::fromBase($difference, $line->item),
                        $line,
                        $by,
                    );
                }
            }

            $count->update([
                'status' => 'applied',
                'counted_at' => now(),
                'note' => $note,
            ]);

            return $count->fresh(['lines.item']);
        });
    }

    /**
     * A branch bought something itself in an emergency. Stock only moves once
     * the admin approves it - otherwise a photo of a bill could put anything
     * on the books.
     */
    public function approveLocalPurchase(LocalPurchase $purchase, User $by, ?string $note = null): LocalPurchase
    {
        if ($purchase->status !== 'waiting') {
            throw new StockException('This local purchase has already been decided.');
        }

        return DB::transaction(function () use ($purchase, $by, $note) {
            $quantity = Quantity::fromBase($purchase->qty, $purchase->item);

            // Price per base unit, so stock is valued the same way a supplier
            // purchase would be.
            $unitCost = $quantity->baseUnits > 0
                ? round(((float) $purchase->amount) / $quantity->baseUnits, 4)
                : null;

            $this->ledger->purchase($purchase->branch_id, $quantity, $purchase, $unitCost, $by);

            $purchase->update([
                'status' => 'approved',
                'approved_by' => $by->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            return $purchase->fresh();
        });
    }

    public function rejectLocalPurchase(LocalPurchase $purchase, User $by, string $note): LocalPurchase
    {
        if ($purchase->status !== 'waiting') {
            throw new StockException('This local purchase has already been decided.');
        }

        $purchase->update([
            'status' => 'rejected',
            'approved_by' => $by->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        return $purchase->fresh();
    }
}
