<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-side screens. A branch user who edits the URL gets a 403, not a
 * glimpse of another branch's data.
 */
class EnsureAdminSide
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdminSide(), 403);

        return $next($request);
    }
}
