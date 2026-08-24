<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable row per stock movement. This table is the truth.
 *
 * Only StockLedgerService writes here. Nothing updates a row and nothing
 * deletes one - a mistake is corrected by writing an opposite movement with a
 * reason, so the history stays readable.
 */
class StockLedger extends Model
{
    /** @use HasFactory<\Database\Factories\StockLedgerFactory> */
    use BelongsToBranch, HasFactory;

    protected $table = 'stock_ledger';

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'branch_id',
        'item_id',
        'qty_delta',
        'movement_type',
        'reference_type',
        'reference_id',
        'unit_cost',
        'balance_after',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty_delta' => 'integer',
            'balance_after' => 'integer',
            'movement_type' => MovementType::class,
            'unit_cost' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOfType(Builder $query, MovementType $type): Builder
    {
        return $query->where('movement_type', $type);
    }

    public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
