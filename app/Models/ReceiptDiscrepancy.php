<?php

namespace App\Models;

use App\Enums\DiscrepancyReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Proof that less arrived than was sent. Without this row nobody can say what
 * happened, and the branch gets blamed later.
 */
class ReceiptDiscrepancy extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\ReceiptDiscrepancyFactory> */
    use HasFactory, InteractsWithMedia;

    /** @var list<string> */
    protected $fillable = ['request_line_id', 'qty_short', 'reason', 'note', 'reported_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty_short' => 'integer',
            'reason' => DiscrepancyReason::class,
        ];
    }

    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(RequestLine::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
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
