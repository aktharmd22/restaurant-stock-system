<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Profile', [
            'person' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'branch' => $user->branch?->name,
                'role' => $user->getRoleNames()->first(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at'),
            ],
            'phone' => [
                'required', 'string', 'max:20',
                Rule::unique('users', 'phone')->ignore($user->id)->whereNull('deleted_at'),
            ],
        ], [
            'name.required' => 'Enter your name.',
            'phone.required' => 'Enter your phone number. You sign in with it.',
            'phone.unique' => 'Someone else already uses that phone number.',
        ]);

        $validated['phone'] = preg_replace('/[^0-9+]/', '', $validated['phone']);

        $user->update($validated);

        return back()->with('success', 'Saved.');
    }
}
