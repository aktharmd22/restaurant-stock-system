<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password reset by a code sent to the phone. These users forget passwords and
 * do not check email, so an emailed link would strand them.
 */
class PasswordOtpController extends Controller
{
    private const CODE_LIFETIME_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function send(Request $request, SmsSender $sms): RedirectResponse
    {
        $validated = $request->validate(
            ['phone' => ['required', 'string', 'max:20']],
            ['phone.required' => 'Enter your phone number.'],
        );

        $phone = preg_replace('/[^0-9+]/', '', $validated['phone']);

        $user = User::query()->active()->where('phone', $phone)->first();

        // This is an internal app where accounts are created by the admin, so a
        // clear answer beats a vague one. Being told "we do not have that
        // number" saves a phone call that "check your messages" would cause.
        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => 'We do not have that phone number. Ask your admin to check it.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_otps')->where('phone', $phone)->delete();
        DB::table('password_reset_otps')->insert([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sms->send($phone, "{$code} is your code to set a new password. It works for ".self::CODE_LIFETIME_MINUTES.' minutes.');

        $request->session()->put('otp_phone', $phone);

        // Without a real SMS provider the code would be unreachable, so in
        // local development it is shown on screen instead.
        if (app()->isLocal()) {
            $request->session()->flash('dev_code', $code);
        }

        return redirect()->route('password.code')
            ->with('info', 'We sent a code to your phone.');
    }

    public function verify(Request $request): Response|RedirectResponse
    {
        $phone = $request->session()->get('otp_phone');

        if (! $phone) {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/VerifyCode', [
            'phone' => $phone,
            'devCode' => $request->session()->get('dev_code'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $phone = $request->session()->get('otp_phone');

        if (! $phone) {
            return redirect()->route('password.request');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'code.required' => 'Enter the 6-digit code.',
            'code.size' => 'The code is 6 digits.',
            'password.required' => 'Choose a new password.',
            'password.min' => 'Use at least 8 characters.',
            'password.confirmed' => 'Both passwords must be the same.',
        ]);

        $record = DB::table('password_reset_otps')->where('phone', $phone)->first();

        if (! $record || now()->greaterThan($record->expires_at)) {
            throw ValidationException::withMessages([
                'code' => 'That code has run out. Ask for a new one.',
            ]);
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            DB::table('password_reset_otps')->where('phone', $phone)->delete();

            throw ValidationException::withMessages([
                'code' => 'Too many wrong tries. Ask for a new code.',
            ]);
        }

        if (! Hash::check($validated['code'], $record->code_hash)) {
            DB::table('password_reset_otps')->where('phone', $phone)->increment('attempts');

            throw ValidationException::withMessages([
                'code' => 'That code is not right. Check your messages and try again.',
            ]);
        }

        $user = User::query()->active()->where('phone', $phone)->firstOrFail();
        $user->forceFill(['password' => $validated['password']])->save();

        DB::table('password_reset_otps')->where('phone', $phone)->delete();
        $request->session()->forget('otp_phone');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Your new password is saved.');
    }
}
