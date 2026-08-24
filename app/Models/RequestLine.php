<?php

namespace App\Models;

use App\Enums\LineStatus;
use App\Enums\ReasonCode;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestLine extends Model
{
    /** @use HasFactory<\Database\Factories\RequestLineFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'request_id',
        'item_id',
        'qty_requested',
        'qty_approved',
        'qty_sent',
        'qty_received',
        'line_status',
        'reason_code',
        'admin_note',
        'branch_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty_requested' => 'integer',
            'qty_approved' => 'integer',
            'qty_sent' => 'integer',
            'qty_received' => 'integer',
            'line_status' => LineStatus::class,
            'reason_code' => ReasonCode::class,
        ];
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(ReceiptDiscrepancy::class);
    }

    /*
    |--------------------------------------------------------------------------
    | The four quantities
    |--------------------------------------------------------------------------
    */

    public function requested(): Quantity
    {
        return Quantity::fromBase($this->qty_requested, $this->item);
    }

    public function approved(): Quantity
    {
        return Quantity::fromBase($this->qty_approved ?? 0, $this->item);
    }

    public function sent(): Quantity
    {
        return Quantity::fromBase($this->qty_sent ?? 0, $this->item);
    }

    public function received(): Quantity
    {
        return Quantity::fromBase($this->qty_received ?? 0, $this->item);
    }

    /** How much less arrived than was sent. */
    public function shortBase(): int
    {
        return max(0, ($this->qty_sent ?? 0) - ($this->qty_received ?? 0));
    }

    public function wasCut(): bool
    {
        return in_array($this->line_status, [LineStatus::Reduced, LineStatus::Rejected], true);
    }

    /** The reason the branch reads, in plain words. */
    public function reasonText(): ?string
    {
        if (! $this->reason_code) {
            return null;
        }

        return $this->reason_code === ReasonCode::Other
            ? ($this->admin_note ?: $this->reason_code->label())
            : $this->reason_code->label();
    }
}
