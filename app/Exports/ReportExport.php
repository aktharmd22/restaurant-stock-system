<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One export class for every report, driven by the report's own column
 * definition. Adding a report adds no code here.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private readonly array $report)
    {
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_column($this->report['columns'], 'label');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $keys = array_column($this->report['columns'], 'key');

        $rows = collect($this->report['rows'])
            ->map(fn ($row) => array_map(fn (string $key) => data_get($row, $key), $keys))
            ->all();

        // The summary lines go at the bottom, after a blank row, so the sheet
        // can still be sorted and filtered.
        if (! empty($this->report['totals'])) {
            $rows[] = array_fill(0, count($keys), '');

            foreach ($this->report['totals'] as $label => $value) {
                $line = array_fill(0, count($keys), '');
                $line[0] = $label;
                $line[1] = $value;
                $rows[] = $line;
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return substr($this->report['title'], 0, 31);
    }
}
