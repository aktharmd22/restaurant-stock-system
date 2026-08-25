<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Per-branch par level and reorder level, both in base units.
 * Par level drives the suggested quantity; reorder level drives "running low".
 */
class BranchItemSetting extends Model
{
    /** @use HasFactory<\Database\Factories\BranchItemSettingFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    /** @var list<string> */
    protected $fillable = ['branch_id', 'item_id', 'par_level', 'reorder_level'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'par_level' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Par levels drive the suggested quantity on every branch request, so a
     * quiet change here changes what every branch asks for tomorrow.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['par_level', 'reorder_level'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
