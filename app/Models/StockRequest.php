<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A branch asking the main store for stock.
 *
 * Named StockRequest rather than Request so it can never be confused with
 * Illuminate\Http\Request - that collision would cost an hour a week forever.
 */
class StockRequest extends Model
{
    /** @use HasFactory<\Database\Factories\StockRequestFactory> */
    use BelongsToBranch, HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'requests';

    /** @var list<string> */
    protected $fillable = [
        'request_number',
        'from_branch_id',
        'to_branch_id',
        'status',
        'needed_by',
        'note',
        'is_late',
        'cutoff_at',
        'created_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'dispatched_by',
        'dispatched_at',
        'received_by',
        'received_at',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'needed_by' => 'date',
            'is_late' => 'boolean',
            'cutoff_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** Branch users see the requests their own branch sent. */
    public function branchColumn(): string
    {
        return 'from_branch_id';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RequestLine::class, 'request_id');
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function dispatchNote(): HasOne
    {
        return $this->hasOne(DispatchNote::class, 'request_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Waiting);
    }

    public function scopeAwaitingDispatch(Builder $query): Builder
    {
        return $query->whereIn('status', [RequestStatus::Approved, RequestStatus::Partial]);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Sent);
    }

    /** Late first, then oldest first: the admin works top to bottom. */
    public function scopeMostUrgentFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_late')->orderBy('submitted_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'reviewed_by', 'dispatched_by', 'received_by', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
