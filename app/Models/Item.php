<?php

namespace App\Models;

use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Item extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\ItemFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'name',
        'base_unit',
        'order_unit',
        'conversion_factor',
        'step_x100',
        'is_perishable',
        'shelf_life_days',
        'storage_location',
        'sort_order',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'conversion_factor' => 'integer',
            'step_x100' => 'integer',
            'is_perishable' => 'boolean',
            'shelf_life_days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function branchSettings(): HasMany
    {
        return $this->hasMany(BranchItemSetting::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /*
    |--------------------------------------------------------------------------
    | Units
    |--------------------------------------------------------------------------
    */

    public function quantity(int $baseUnits): Quantity
    {
        return Quantity::fromBase($baseUnits, $this);
    }

    public function fromOrderUnit(float|int|string $amount): Quantity
    {
        return Quantity::fromOrderUnit($amount, $this);
    }

    /** How far one tap of the stepper moves, in order units. */
    public function stepSize(): float
    {
        return $this->step_x100 / 100;
    }

    /** How many decimal places to show. Pieces are never half. */
    public function decimals(): int
    {
        return $this->order_unit === 'piece' || $this->order_unit === 'dozen' ? 0 : 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Photo
    |--------------------------------------------------------------------------
    */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Not queued: shared hosting runs queues from cron, and a photo that
        // appears minutes later reads as a broken app.
        $this->addMediaConversion('thumb')
            ->width(160)
            ->height(160)
            ->nonQueued();
    }

    public function photoUrl(): ?string
    {
        $media = $this->getFirstMedia('photo');

        return $media ? $media->getUrl('thumb') : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'category_id', 'order_unit', 'conversion_factor', 'is_active'])
            ->logOnlyDirty();
    }
}
