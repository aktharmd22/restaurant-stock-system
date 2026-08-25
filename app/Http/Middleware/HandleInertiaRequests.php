<?php

namespace App\Http\Middleware;

use App\Enums\RequestStatus;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Strict lazy-loading is on outside production. These two are read on
        // every single request, so load them once here rather than tripping
        // over them in twenty different controllers.
        $user?->loadMissing(['branch', 'roles']);

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->firstName(),
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'roles' => $user->getRoleNames(),
                    'is_admin_side' => $user->isAdminSide(),
                    'sound_enabled' => $user->sound_enabled,
                    'sound_volume' => $user->sound_volume,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                        'code' => $user->branch->code,
                        'type' => $user->branch->type,
                    ] : null,
                ] : null,
            ],

            'business' => [
                'name' => setting('business_name'),
                'tagline' => setting('business_tagline'),
                'currency' => setting('currency_symbol'),
            ],

            // One-line messages after an action. Always plain English, and the
            // wording always matches the button that was pressed.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),

                // What an Excel import did, row by row, so the screen can show
                // exactly which lines still need a person to look at them.
                'import' => fn () => $request->session()->get('import'),
            ],

            /*
             * What is waiting, and what the last alert said. The polling
             * fallback watches `unread` to know something happened while the
             * screen was idle, and `pending` drives the tab badge and the
             * five-minute nag.
             */
            'alerts' => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'latest' => $user->unreadNotifications()->latest()->first()?->data,
                'pending' => $this->pendingCount($user),
            ] : null,

            // Reverb cannot run on shared hosting. When no broadcast driver is
            // configured the frontend falls back to polling, and everything
            // still works - just a few seconds slower.
            'realtime' => [
                'driver' => config('broadcasting.default'),
                'key' => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'poll_seconds' => (int) env('VITE_POLL_SECONDS', 12),
            ],
        ];
    }

    /**
     * What is actually sitting there needing someone: requests to look at for
     * the admin, deliveries to confirm for a branch.
     */
    private function pendingCount(User $user): int
    {
        if ($user->isAdminSide()) {
            return StockRequest::where('status', RequestStatus::Waiting)->count();
        }

        if (! $user->branch_id) {
            return 0;
        }

        // The branch scope narrows this to their own branch automatically.
        return StockRequest::where('status', RequestStatus::Sent)->count();
    }
}
