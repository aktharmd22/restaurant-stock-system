<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Branch scoping, enforced in the database layer rather than hidden in the UI.
 *
 * A branch user's every query is narrowed to their own branch automatically, so
 * a hand-edited URL or a forgotten `where` cannot leak another branch's data.
 * Admin-side users are unrestricted, which is the whole point of their job.
 *
 * Use `withoutBranchScope()` deliberately when a job or command must cross
 * branches - it is meant to be visible in a code review.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            $user = Auth::user();

            if (! $user || $user->isAdminSide() || ! $user->branch_id) {
                return;
            }

            $model = $query->getModel();

            $query->where(
                $model->qualifyColumn($model->branchColumn()),
                $user->branch_id,
            );
        });
    }

    /** Which column holds the owning branch. Override where it differs. */
    public function branchColumn(): string
    {
        return 'branch_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Branch::class, $this->branchColumn());
    }

    /** @return Builder<static> */
    public static function withoutBranchScope(): Builder
    {
        return static::withoutGlobalScope('branch');
    }

    /** @return Builder<static> */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where($this->qualifyColumn($this->branchColumn()), $branchId);
    }
}
