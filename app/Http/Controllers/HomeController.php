<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One address after sign-in. Where it lands depends on who you are, so nobody
 * has to remember a URL.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user()->isAdminSide()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('branch.home');
    }
}
