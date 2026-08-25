<?php

use App\Support\Settings;

if (! function_exists('setting')) {
    /**
     * Read an admin-editable setting: setting('business_name').
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings->all() : $settings->get($key, $default);
    }
}

if (! function_exists('money_in')) {
    /**
     * Indian grouping: 52,98,583 rather than 5,298,583. Two places below a
     * lakh so paise stay visible on small amounts, whole rupees above it,
     * because nobody reads paise on a five-figure total.
     */
    function money_in(float $amount, ?int $decimals = null): string
    {
        $decimals ??= abs($amount) < 100000 ? 2 : 0;

        $sign = $amount < 0 ? '-' : '';

        // Round first: 99.999 is one hundred rupees, not ninety-nine.
        $rounded = number_format(abs($amount), $decimals, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $rounded), 2, null);

        // Last three digits, then pairs: 1,23,45,678.
        if (strlen($whole) > 3) {
            $last = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last;
        }

        return $sign.setting('currency_symbol', '₹').$whole.($fraction === null ? '' : '.'.$fraction);
    }
}
