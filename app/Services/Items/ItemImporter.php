<?php

namespace App\Services\Items;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Reads a filled-in item template and turns it into items.
 *
 * Nothing is written until every row has been read, and a row that cannot be
 * understood is reported with its row number and the reason in plain English
 * rather than being silently dropped. A restaurant uploading sixty items needs
 * to know exactly which two it has to fix.
 */
class ItemImporter
{
    /** The column headings the template ships with, in order. */
    public const COLUMNS = [
        'name' => 'Item name',
        'group' => 'Group',
        'order_unit' => 'Ordered by',
        'base_unit' => 'Counted in',
        'conversion_factor' => 'How many counted units in one ordered unit',
        'step' => 'Step',
        'is_perishable' => 'Goes off (yes/no)',
        'shelf_life_days' => 'Days it keeps',
        'storage_location' => 'Where it is kept',
    ];

    public const BASE_UNITS = ['g', 'ml', 'piece'];

    public const ORDER_UNITS = ['kg', 'g', 'litre', 'ml', 'sack', 'piece', 'dozen', 'packet'];

    /**
     * @return array{added: int, updated: int, skipped: int, problems: array<int, array{row: int, name: string, problem: string}>}
     */
    public function import(UploadedFile $file, bool $updateExisting = true): array
    {
        $sheets = Excel::toArray(null, $file);
        $rows = $sheets[0] ?? [];

        $heading = $this->headingMap(array_shift($rows) ?? []);

        $categories = Category::withTrashed()->get()->keyBy(fn (Category $c) => Str::lower($c->name));
        $existing = Item::withTrashed()->get()->keyBy(fn (Item $i) => Str::lower($i->name));

        $ready = [];
        $problems = [];

        foreach ($rows as $index => $row) {
            // +2: one for the heading row, one because people count from 1.
            $number = $index + 2;
            $values = $this->readRow($row, $heading);

            if ($this->isBlank($values)) {
                continue;
            }

            $parsed = $this->parse($values, $categories);

            if (isset($parsed['problem'])) {
                $problems[] = [
                    'row' => $number,
                    'name' => (string) ($values['name'] ?? ''),
                    'problem' => $parsed['problem'],
                ];

                continue;
            }

            $key = Str::lower($parsed['attributes']['name']);

            // The same name twice in one file: the later row wins, and we say so.
            if (isset($ready[$key])) {
                $problems[] = [
                    'row' => $number,
                    'name' => $parsed['attributes']['name'],
                    'problem' => 'This name is in the file more than once. Only the last one was used.',
                ];
            }

            $ready[$key] = $parsed['attributes'];
        }

        return $this->write($ready, $existing, $updateExisting, $problems);
    }

    /**
     * @param  array<string, array<string, mixed>>  $ready
     * @param  \Illuminate\Support\Collection<string, Item>  $existing
     * @param  array<int, array{row: int, name: string, problem: string}>  $problems
     * @return array{added: int, updated: int, skipped: int, problems: array<int, array{row: int, name: string, problem: string}>}
     */
    private function write(array $ready, $existing, bool $updateExisting, array $problems): array
    {
        $added = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($ready, $existing, $updateExisting, &$added, &$updated, &$skipped) {
            foreach ($ready as $key => $attributes) {
                $item = $existing->get($key);

                if (! $item) {
                    Item::create($attributes);
                    $added++;

                    continue;
                }

                if (! $updateExisting) {
                    $skipped++;

                    continue;
                }

                // A name that was deleted before comes back rather than clashing.
                if ($item->trashed()) {
                    $item->restore();
                }

                $item->update($attributes);
                $updated++;
            }
        });

        return [
            'added' => $added,
            'updated' => $updated,
            'skipped' => $skipped,
            'problems' => array_slice($problems, 0, 50),
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $heading
     * @return array<string, string>
     */
    private function readRow(array $row, array $heading): array
    {
        $values = [];

        foreach ($heading as $field => $position) {
            $values[$field] = trim((string) ($row[$position] ?? ''));
        }

        return $values;
    }

    /** @param array<string, string> $values */
    private function isBlank(array $values): bool
    {
        return collect($values)->every(fn (string $value) => $value === '');
    }

    /**
     * @param  array<string, string>  $values
     * @param  \Illuminate\Support\Collection<string, Category>  $categories
     * @return array{attributes?: array<string, mixed>, problem?: string}
     */
    private function parse(array $values, $categories): array
    {
        $name = $values['name'] ?? '';

        if ($name === '') {
            return ['problem' => 'No item name.'];
        }

        if (mb_strlen($name) > 60) {
            return ['problem' => 'The name is longer than 60 letters.'];
        }

        $category = $categories->get(Str::lower($values['group'] ?? ''));

        if (! $category) {
            return ['problem' => "There is no group called \"{$values['group']}\". Add the group first, or fix the spelling."];
        }

        $orderUnit = Str::lower($values['order_unit'] ?? '');

        if (! in_array($orderUnit, self::ORDER_UNITS, true)) {
            return ['problem' => 'Ordered by must be one of: '.implode(', ', self::ORDER_UNITS).'.'];
        }

        $baseUnit = Str::lower($values['base_unit'] ?? '');

        if (! in_array($baseUnit, self::BASE_UNITS, true)) {
            return ['problem' => 'Counted in must be g, ml or piece.'];
        }

        $factor = (int) round((float) str_replace(',', '', $values['conversion_factor'] ?? ''));

        if ($factor < 1 || $factor > 1000000) {
            return ['problem' => 'How many counted units in one ordered unit must be a whole number, at least 1.'];
        }

        $step = (float) str_replace(',', '', $values['step'] ?? '');
        $step = $step > 0 ? $step : 1;

        if ($step > 10000) {
            return ['problem' => 'The step is too big.'];
        }

        $perishable = $this->yesNo($values['is_perishable'] ?? '');
        $shelfLife = (int) ($values['shelf_life_days'] ?? 0);

        if ($perishable && ($shelfLife < 1 || $shelfLife > 3650)) {
            return ['problem' => 'It goes off, so say how many days it keeps.'];
        }

        return [
            'attributes' => [
                'name' => $name,
                'category_id' => $category->id,
                'base_unit' => $baseUnit,
                'order_unit' => $orderUnit,
                'conversion_factor' => $factor,
                'step_x100' => max(1, (int) round($step * 100)),
                'is_perishable' => $perishable,
                'shelf_life_days' => $perishable ? $shelfLife : null,
                'storage_location' => ($values['storage_location'] ?? '') ?: null,
                'is_active' => true,
            ],
        ];
    }

    private function yesNo(string $value): bool
    {
        return in_array(Str::lower(trim($value)), ['yes', 'y', 'true', '1'], true);
    }

    /**
     * Maps the sheet's own heading row onto our fields, so a column that has
     * been moved or renamed slightly still lands in the right place.
     *
     * @param  array<int, mixed>  $row
     * @return array<string, int>
     */
    private function headingMap(array $row): array
    {
        $normalise = fn (string $text) => Str::of($text)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value();

        $seen = [];

        foreach ($row as $position => $label) {
            $seen[$normalise((string) $label)] = $position;
        }

        $map = [];
        $fallback = 0;

        foreach (self::COLUMNS as $field => $label) {
            $key = $normalise($label);
            $map[$field] = $seen[$key] ?? $fallback;
            $fallback++;
        }

        return $map;
    }
}
