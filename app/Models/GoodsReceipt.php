<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delivery against one purchase order line. A line may have several.
 */
class GoodsReceipt extends Model
{
    /** @var list<string> */
    protected $fillable = ['purchase_order_line_id', 'qty', 'received_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
