<?php

namespace App\Exports;

use App\Models\Category;
use App\Services\Items\ItemImporter;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The blank sheet a restaurant fills in.
 *
 * Two sheets: the one to type into, already carrying two worked examples, and
 * a reference sheet listing the groups and units that exist, so nobody has to
 * guess what to write and have the upload rejected for it.
 */
class ItemTemplateExport implements WithMultipleSheets
{
    use Exportable;

    /** @return array<int, object> */
    public function sheets(): array
    {
        return [new ItemTemplateSheet, new ItemTemplateReferenceSheet];
    }
}

class ItemTemplateSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Items';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_values(ItemImporter::COLUMNS);
    }

    /** @return array<int, array<int, string|int>> */
    public function array(): array
    {
        $group = Category::active()->ordered()->first()?->name ?? 'Vegetables';

        return [
            ['Onion', $group, 'kg', 'g', 1000, 0.5, 'no', '', 'Cold room'],
            ['Milk', $group, 'litre', 'ml', 1000, 1, 'yes', 3, 'Fridge'],
        ];
    }

    /** @return array<int|string, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        // The examples sit in grey so nobody mistakes them for real rows.
        $sheet->getStyle('A2:I3')->getFont()->getColor()->setARGB('FF9AA0A6');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

class ItemTemplateReferenceSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'What you can write';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Groups that exist', 'Ordered by', 'Counted in'];
    }

    /** @return array<int, array<int, string>> */
    public function array(): array
    {
        $groups = Category::active()->ordered()->pluck('name')->values()->all();
        $order = ItemImporter::ORDER_UNITS;
        $base = ItemImporter::BASE_UNITS;

        $rows = [];
        $length = max(count($groups), count($order), count($base));

        for ($i = 0; $i < $length; $i++) {
            $rows[] = [$groups[$i] ?? '', $order[$i] ?? '', $base[$i] ?? ''];
        }

        return $rows;
    }

    /** @return array<int|string, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
