<?php

namespace App\Services\Stock;

use App\Exceptions\StockException;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * Approving a line promises stock to a branch. That promise has to be visible
 * to the next admin who looks, or two branches get promised the same 20 kg and
 * one of them finds out at 7pm on a Friday.
 *
 * Reserved stock is still physically in the main store, so qty_on_hand does
 * not move until dispatch. What changes is what is left to promise:
 *
 *     available = qty_on_hand - qty_reserved
 */
class ReservationService
{
    public function __construct(private readonly StockLedgerService $ledger)
    {
    }

    /**
     * Promise stock to someone. Fails if there is not enough left to promise.
     *
     * The availability check happens INSIDE the row lock, never against
     * whatever the admin's screen was showing a minute ago.
     */
    public function reserve(int $branchId, Quantity $quantity): void
    {
        if ($quantity->isZero()) {
            return;
        }

        DB::transaction(function () use ($branchId, $quantity) {
            $balance = $this->ledger->lockBalance($branchId, $quantity->item->id);

            if ($balance->availableBase() < $quantity->baseUnits) {
                throw StockException::notEnoughStock(
                    $quantity->item->name,
                    $balance->available()->forDisplay(),
                    $quantity->forDisplay(),
                );
            }

            $balance->qty_reserved += $quantity->baseUnits;
            $balance->updated_at = now();
            $balance->save();
        });
    }

    /**
     * Let a promise go without moving any stock - used when an approved
     * request is cancelled.
     */
    public function release(int $branchId, Quantity $quantity): void
    {
        if ($quantity->isZero()) {
            return;
        }

        DB::transaction(function () use ($branchId, $quantity) {
            $balance = $this->ledger->lockBalance($branchId, $quantity->item->id);

            // Never below zero: a reservation released twice must not turn into
            // free stock that does not exist.
            $balance->qty_reserved = max(0, $balance->qty_reserved - $quantity->baseUnits);
            $balance->updated_at = now();
            $balance->save();
        });
    }

    /**
     * How much of this item can still be promised.
     */
    public function availableBase(int $branchId, int $itemId): int
    {
        return DB::transaction(fn () => $this->ledger->lockBalance($branchId, $itemId)->availableBase());
    }
}
