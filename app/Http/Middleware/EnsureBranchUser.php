<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Branch screens need a branch. An admin without one would see an empty app,
 * so they are sent to the admin side instead.
 */
class EnsureBranchUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        if ($user->branch_id === null) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
