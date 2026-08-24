<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The restaurant name, tagline and contact details. These appear on the login
 * screen, in the app header and on every PDF, so the admin can change them
 * without anyone touching the code.
 */
class BusinessSettingsController extends Controller
{
    public function edit(Settings $settings): Response
    {
        return Inertia::render('Admin/Settings/Business', [
            'values' => [
                'business_name' => $settings->get('business_name'),
                'business_tagline' => $settings->get('business_tagline'),
                'business_phone' => $settings->get('business_phone'),
                'business_address' => $settings->get('business_address'),
                'currency_symbol' => $settings->get('currency_symbol'),
            ],
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:60'],
            'business_tagline' => ['nullable', 'string', 'max:120'],
            'business_phone' => ['nullable', 'string', 'max:20'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'currency_symbol' => ['required', 'string', 'max:4'],
        ], [
            'business_name.required' => 'Enter the restaurant name.',
            'business_name.max' => 'Keep the name under 60 characters so it fits on a phone.',
        ]);

        $settings->setMany(array_map(fn ($value) => $value ?? '', $validated));

        return back()->with('success', 'Name saved.');
    }
}
