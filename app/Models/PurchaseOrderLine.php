<?php

namespace App\Models;

use App\Support\Quantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    /** @use HasFactory<\Database\Factories\PurchaseOrderLineFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['purchase_order_id', 'item_id', 'qty_ordered', 'qty_received', 'unit_price'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty_ordered' => 'integer',
            'qty_received' => 'integer',
            'unit_price' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** A line can be delivered more than once. */
    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function ordered(): Quantity
    {
        return Quantity::fromBase($this->qty_ordered, $this->item);
    }

    public function outstandingBase(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }

    /** Price is per base unit, so the line total needs no conversion. */
    public function lineTotal(): float
    {
        return round($this->qty_ordered * (float) $this->unit_price, 2);
    }
}
