<?php

namespace App\Models;

use App\Enums\WastageReason;
use App\Models\Concerns\BelongsToBranch;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Wastage extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\WastageFactory> */
    use BelongsToBranch, HasFactory, InteractsWithMedia;

    protected $table = 'wastage';

    /** @var list<string> */
    protected $fillable = ['branch_id', 'item_id', 'qty', 'reason', 'note', 'recorded_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'reason' => WastageReason::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function quantity(): Quantity
    {
        return Quantity::fromBase($this->qty, $this->item);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(400)->nonQueued();
    }

    public function photoUrl(): ?string
    {
        return $this->getFirstMedia('photo')?->getUrl('thumb');
    }
}
