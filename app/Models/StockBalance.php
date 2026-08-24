<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached total, rebuilt from the ledger by `stock:rebuild-balances`.
 * Never edited by hand, never written outside StockLedgerService.
 */
class StockBalance extends Model
{
    /** @use HasFactory<\Database\Factories\StockBalanceFactory> */
    use BelongsToBranch, HasFactory;

    public const CREATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['branch_id', 'item_id', 'qty_on_hand', 'qty_reserved', 'avg_cost'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'integer',
            'qty_reserved' => 'integer',
            'avg_cost' => 'decimal:4',
            'updated_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * What can actually be promised to someone. Stock that is approved but not
     * yet dispatched belongs to another branch already.
     */
    public function availableBase(): int
    {
        return $this->qty_on_hand - $this->qty_reserved;
    }

    public function available(): Quantity
    {
        return Quantity::fromBase($this->availableBase(), $this->item);
    }

    public function onHand(): Quantity
    {
        return Quantity::fromBase($this->qty_on_hand, $this->item);
    }

    public function scopeWithStock(Builder $query): Builder
    {
        return $query->where('qty_on_hand', '>', 0);
    }
}
