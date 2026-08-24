<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    /** @use HasFactory<\Database\Factories\StockCountFactory> */
    use BelongsToBranch, HasFactory;

    /** @var list<string> */
    protected $fillable = ['branch_id', 'counted_by', 'status', 'counted_at', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['counted_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
