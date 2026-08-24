<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchNote extends Model
{
    /** @use HasFactory<\Database\Factories\DispatchNoteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'request_id', 'note_number', 'sent_by', 'carrier_name', 'vehicle_number', 'sent_at', 'pdf_path',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'request_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
