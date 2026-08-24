<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    /** @use HasFactory<\Database\Factories\StockCountLineFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['stock_count_id', 'item_id', 'system_qty', 'counted_qty', 'difference', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'system_qty' => 'integer',
            'counted_qty' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
