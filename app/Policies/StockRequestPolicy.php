<?php

namespace App\Policies;

use App\Models\StockRequest;
use App\Models\User;

/**
 * Branch scoping is enforced twice on purpose: a global scope narrows every
 * query, and this policy checks the branch again on the way in. Either one
 * alone would be enough on a good day; both are needed on a bad one.
 */
class StockRequestPolicy
{
    public function view(User $user, StockRequest $request): bool
    {
        return $user->isAdminSide() || $user->branch_id === $request->from_branch_id;
    }

    public function create(User $user): bool
    {
        return $user->can('requests.create') && $user->branch_id !== null;
    }

    public function approve(User $user, StockRequest $request): bool
    {
        return $user->can('requests.approve') && $request->status->needsReview();
    }

    public function dispatch(User $user, StockRequest $request): bool
    {
        return $user->can('requests.dispatch') && $request->status->awaitingDispatch();
    }

    public function receive(User $user, StockRequest $request): bool
    {
        return $user->can('requests.receive')
            && $user->branch_id === $request->from_branch_id
            && $request->status->inTransit();
    }

    /**
     * A branch can call its own request back until someone has acted on it.
     * An admin can cancel anything that has not left the store.
     */
    public function cancel(User $user, StockRequest $request): bool
    {
        if (! $request->status->canBeCancelled()) {
            return false;
        }

        if ($user->isAdminSide()) {
            return $user->can('requests.cancel');
        }

        return $user->can('requests.cancel') && $user->branch_id === $request->from_branch_id;
    }
}
