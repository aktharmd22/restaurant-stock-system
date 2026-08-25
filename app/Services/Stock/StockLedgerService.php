<?php

namespace App\Services\Stock;

use App\Enums\MovementType;
use App\Exceptions\StockException;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The only class in this application allowed to write stock_ledger or
 * stock_balances. Everything else asks it to.
 *
 * The ledger is the truth: one immutable row per movement, never updated,
 * never deleted. stock_balances is a cache of it and can be thrown away and
 * rebuilt at any time with `php artisan stock:rebuild-balances`.
 *
 * An architecture test enforces the "only writer" rule, because a rule that is
 * not tested is a rule that will be broken in six months.
 */
class StockLedgerService
{
    /**
     * Record one movement. The quantity is a SIGNED delta: negative removes.
     *
     * Safe to call twice with the same reference - the second call returns the
     * movement already recorded rather than moving stock again. Branch staff on
     * bad connections double-tap, and retries happen.
     */
    public function record(
        int $branchId,
        Quantity $delta,
        MovementType $type,
        ?Model $reference = null,
        ?float $unitCost = null,
        ?User $by = null,
        bool $allowNegative = false,
        ?string $note = null,
    ): StockLedger {
        $referenceType = $reference ? class_basename($reference) : null;
        $referenceId = $reference?->getKey();

        return DB::transaction(function () use (
            $branchId, $delta, $type, $referenceType, $referenceId, $unitCost, $by, $allowNegative, $note
        ) {
            $balance = $this->lockBalance($branchId, $delta->item->id);

            $newQty = $balance->qty_on_hand + $delta->baseUnits;

            if ($newQty < 0 && ! $allowNegative) {
                throw StockException::notEnoughStock(
                    $delta->item->name,
                    $balance->onHand()->forDisplay(),
                    $delta->negated()->forDisplay(),
                );
            }

            try {
                $movement = StockLedger::create([
                    'branch_id' => $branchId,
                    'item_id' => $delta->item->id,
                    'qty_delta' => $delta->baseUnits,
                    'movement_type' => $type,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'note' => $note,
                    'unit_cost' => $unitCost,
                    'balance_after' => $newQty,
                    'created_by' => $by?->id,
                ]);
            } catch (QueryException $exception) {
                // Duplicate key on the idempotency index: this exact movement
                // is already recorded, so the stock has already moved.
                if ($this->isDuplicate($exception) && $referenceType !== null) {
                    return $this->existingMovement($branchId, $delta->item->id, $type, $referenceType, $referenceId);
                }

                throw $exception;
            }

            // Order matters: the new average is weighted against the quantity
            // held BEFORE this movement, so it must be worked out first.
            $balance->avg_cost = $this->newAverageCost($balance, $delta->baseUnits, $unitCost);
            $balance->qty_on_hand = $newQty;
            $balance->updated_at = now();
            $balance->save();

            return $movement;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Named movements - these fix the sign so a caller cannot get it backwards
    |--------------------------------------------------------------------------
    */

    public function purchase(int $branchId, Quantity $quantity, ?Model $reference = null, ?float $unitCost = null, ?User $by = null): StockLedger
    {
        return $this->record($branchId, $this->positive($quantity), MovementType::Purchase, $reference, $unitCost, $by);
    }

    public function transferIn(int $branchId, Quantity $quantity, ?Model $reference = null, ?float $unitCost = null, ?User $by = null): StockLedger
    {
        return $this->record($branchId, $this->positive($quantity), MovementType::TransferIn, $reference, $unitCost, $by);
    }

    /**
     * The unit cost travels with the goods, so a branch's stock is valued at
     * what the main store actually paid for it. Without it, "cost per branch"
     * is guesswork.
     */
    public function transferOut(int $branchId, Quantity $quantity, ?Model $reference = null, ?User $by = null, ?float $unitCost = null): StockLedger
    {
        return $this->record($branchId, $this->positive($quantity)->negated(), MovementType::TransferOut, $reference, $unitCost, $by);
    }

    /** What one base unit of this item is currently worth at a branch. */
    public function averageCost(int $branchId, int $itemId): float
    {
        return (float) (StockBalance::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->value('avg_cost') ?? 0);
    }

    public function consumption(int $branchId, Quantity $quantity, ?Model $reference = null, ?User $by = null): StockLedger
    {
        return $this->record($branchId, $this->positive($quantity)->negated(), MovementType::Consumption, $reference, null, $by, allowNegative: true);
    }

    public function wastage(int $branchId, Quantity $quantity, ?Model $reference = null, ?User $by = null): StockLedger
    {
        return $this->record($branchId, $this->positive($quantity)->negated(), MovementType::Wastage, $reference, null, $by);
    }

    public function returnStock(int $branchId, Quantity $quantity, ?Model $reference = null, ?User $by = null): StockLedger
    {
        return $this->record($branchId, $this->positive($quantity), MovementType::ReturnStock, $reference, null, $by);
    }

    /**
     * A correction after a count. The delta is signed and may go either way,
     * and it is always allowed to take stock negative - the count is what is
     * actually on the shelf, and arguing with it helps nobody.
     */
    public function adjustment(
        int $branchId,
        Quantity $signedDelta,
        ?Model $reference = null,
        ?User $by = null,
        ?string $note = null,
    ): StockLedger {
        return $this->record(
            $branchId,
            $signedDelta,
            MovementType::Adjustment,
            $reference,
            null,
            $by,
            allowNegative: true,
            note: $note,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Balances
    |--------------------------------------------------------------------------
    */

    /**
     * Lock one balance row for update. Callers touching several items MUST
     * take their locks in ascending item id order, or two multi-line approvals
     * running at once can deadlock each other.
     */
    public function lockBalance(int $branchId, int $itemId): StockBalance
    {
        $balance = StockBalance::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        try {
            StockBalance::create([
                'branch_id' => $branchId,
                'item_id' => $itemId,
                'qty_on_hand' => 0,
                'qty_reserved' => 0,
                'avg_cost' => 0,
            ]);
        } catch (QueryException $exception) {
            // Another request created it a moment ago. Fine - read it back.
            if (! $this->isDuplicate($exception)) {
                throw $exception;
            }
        }

        return StockBalance::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Recalculate one balance straight from the ledger. Used by
     * `stock:rebuild-balances` and safe to run at any time.
     */
    public function rebuildBalance(int $branchId, int $itemId): StockBalance
    {
        return DB::transaction(function () use ($branchId, $itemId) {
            $balance = $this->lockBalance($branchId, $itemId);

            $balance->qty_on_hand = (int) StockLedger::withoutBranchScope()
                ->where('branch_id', $branchId)
                ->where('item_id', $itemId)
                ->sum('qty_delta');

            $balance->updated_at = now();
            $balance->save();

            return $balance;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function positive(Quantity $quantity): Quantity
    {
        if ($quantity->isNegative()) {
            throw new StockException('A quantity cannot be less than nothing.');
        }

        return $quantity;
    }

    /**
     * Weighted moving average, updated only when stock comes in with a price.
     * Stock going out is valued at the average already held.
     */
    private function newAverageCost(StockBalance $balance, int $delta, ?float $unitCost): float
    {
        if ($unitCost === null || $delta <= 0) {
            return (float) $balance->avg_cost;
        }

        $existingQty = max(0, $balance->qty_on_hand);
        $totalQty = $existingQty + $delta;

        if ($totalQty <= 0) {
            return $unitCost;
        }

        $existingValue = $existingQty * (float) $balance->avg_cost;

        return round(($existingValue + ($delta * $unitCost)) / $totalQty, 4);
    }

    private function isDuplicate(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }

    private function existingMovement(
        int $branchId,
        int $itemId,
        MovementType $type,
        string $referenceType,
        ?int $referenceId,
    ): StockLedger {
        return StockLedger::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->where('movement_type', $type)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->firstOrFail();
    }
}
