<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Who may listen to what. A branch hears only its own channel; admin-side
 * people hear the main store channel and may also watch any branch.
 */

Broadcast::channel('admin.main', function (User $user) {
    return $user->isAdminSide();
});

Broadcast::channel('branch.{branchId}', function (User $user, int $branchId) {
    return $user->isAdminSide() || (int) $user->branch_id === $branchId;
});
