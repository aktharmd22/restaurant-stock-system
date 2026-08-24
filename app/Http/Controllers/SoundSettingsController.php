<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sound is per person, not per device: the store keeper wants it loud, the
 * branch manager checking on the bus does not.
 */
class SoundSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sound_enabled' => ['required', 'boolean'],
            'sound_volume' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', $validated['sound_enabled'] ? 'Sound is on.' : 'Sound is off.');
    }

    /** Clears the tab badge once someone has actually looked. */
    public function markRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
