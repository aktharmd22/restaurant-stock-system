<?php

namespace App\Services\Sms;

/**
 * SMS is a paid third-party service. The app is written against this interface
 * so the whole flow works today with the log driver, and switching to a real
 * provider is one binding change - no controller or screen has to move.
 */
interface SmsSender
{
    public function send(string $phone, string $message): bool;
}
