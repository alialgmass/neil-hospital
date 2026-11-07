<?php

namespace Modules\Reporting\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TabularReportExport implements FromCollection, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<int|float|string|null>>  $rows
     */
    public function __construct(private readonly array $headings, private readonly array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
