<?php

namespace Modules\Admin\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Blank Excel template (stump) — Arabic headings only, no data rows.
 * Used for the "download template" button so users can fill in data before importing.
 */
class ModuleTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        private readonly array $headings,
        private readonly string $title,
    ) {}

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }
}
