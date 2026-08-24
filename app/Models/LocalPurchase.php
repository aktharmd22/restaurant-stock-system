<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An emergency buy made by a branch, with a photo of the bill. Stock only
 * moves once the admin approves it.
 */
class LocalPurchase extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\LocalPurchaseFactory> */
    use BelongsToBranch, HasFactory, InteractsWithMedia;

    /** @var list<string> */
    protected $fillable = [
        'branch_id', 'item_id', 'qty', 'amount', 'reason', 'status',
        'requested_by', 'approved_by', 'decided_at', 'decision_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'amount' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function quantity(): Quantity
    {
        return Quantity::fromBase($this->qty, $this->item);
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', 'waiting');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bill')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(600)->nonQueued();
    }

    public function billUrl(): ?string
    {
        return $this->getFirstMedia('bill')?->getUrl();
    }
}
