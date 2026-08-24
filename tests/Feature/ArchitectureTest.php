<?php

use Symfony\Component\Finder\Finder;

/**
 * "Only StockLedgerService writes stock" is the single most important rule in
 * this codebase. A rule that is not tested is a rule that gets broken quietly
 * six months from now, by someone in a hurry, and then the numbers stop adding
 * up and nobody knows why.
 *
 * So the rule is a test.
 */

/** Files allowed to write stock tables directly. */
function stockWriters(): array
{
    return [
        'app/Services/Stock/StockLedgerService.php',
        'app/Services/Stock/ReservationService.php',
    ];
}

/** @return array<string, string> path => contents */
function appPhpFiles(): array
{
    $files = [];

    foreach (Finder::create()->files()->name('*.php')->in(base_path('app')) as $file) {
        $relative = str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()));
        $files[$relative] = $file->getContents();
    }

    return $files;
}

it('lets nothing but the stock services write the ledger', function () {
    $offenders = [];

    foreach (appPhpFiles() as $path => $contents) {
        if (in_array($path, stockWriters(), true)) {
            continue;
        }

        // Creating ledger rows, or writing either stock table with raw queries.
        $patterns = [
            '/StockLedger::(create|insert|update|firstOrCreate|updateOrCreate)\s*\(/',
            '/StockBalance::(create|insert|update|firstOrCreate|updateOrCreate)\s*\(/',
            "/DB::table\(['\"]stock_(ledger|balances)['\"]\)[^;]*->(insert|update|delete|upsert|increment|decrement)\s*\(/s",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents)) {
                $offenders[] = $path;
                break;
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        'These files write stock tables directly:',
        ...$offenders,
        'Route the change through StockLedgerService instead, so it lands in the ledger.',
    ]));
});

it('keeps controllers out of the stock tables entirely', function () {
    $offenders = [];

    foreach (appPhpFiles() as $path => $contents) {
        if (! str_starts_with($path, 'app/Http/Controllers/')) {
            continue;
        }

        if (preg_match('/(StockLedger|StockBalance)::(create|update|insert)/', $contents)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('never lets a service take a bare number where it means a quantity', function () {
    // Quantities travel as Quantity objects. If one of these methods ever grows
    // an int|float quantity parameter, this catches it.
    $ledger = file_get_contents(base_path('app/Services/Stock/StockLedgerService.php'));

    foreach (['purchase', 'transferIn', 'transferOut', 'wastage', 'consumption', 'adjustment'] as $method) {
        expect($ledger)->toMatch("/function {$method}\([^)]*Quantity \\\$/s");
    }
});
