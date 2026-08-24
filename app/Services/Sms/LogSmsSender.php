<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes the message to the log instead of sending it. Used until real SMS
 * credentials exist. In local development the code is also surfaced on screen
 * so you can test the reset flow without a provider.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): bool
    {
        Log::channel(config('logging.default'))->info('SMS (not sent - log driver)', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
