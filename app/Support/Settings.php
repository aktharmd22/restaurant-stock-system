<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Small key/value store for things the admin can change without a deploy -
 * starting with the restaurant name, which appears on the login screen,
 * the app header and every PDF.
 *
 * Reads are cached forever and busted on write, so this costs one query
 * per deploy rather than one per request. That matters on shared hosting.
 */
class Settings
{
    private const CACHE_KEY = 'app.settings';

    /** Defaults used until the admin saves something else. */
    public const DEFAULTS = [
        'business_name' => 'Restaurant Stock',
        'business_tagline' => 'Stock, sorted. Every branch, every day.',
        'business_phone' => '',
        'business_address' => '',
        'currency_symbol' => '₹',
        'escalate_after_minutes' => 30,
        'nag_every_minutes' => 5,
    ];

    /** @return array<string, mixed> */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->get()->mapWithKeys(fn (Setting $s) => [
                $s->key => $this->cast($s->value, $s->type),
            ])->all();
        });

        return array_merge(self::DEFAULTS, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $type = match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_array($value) => 'json',
            default => 'string',
        };

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'type' => $type],
        );

        $this->flush();
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }
}
