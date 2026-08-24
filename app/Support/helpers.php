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
