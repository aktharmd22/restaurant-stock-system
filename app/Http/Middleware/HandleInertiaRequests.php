<?php

namespace App\Http\Middleware;

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
            ],

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
}
