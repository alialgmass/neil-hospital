<?php

namespace Modules\Admin\Exports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Generic single-sheet Excel export for a module's core table.
 *
 * Holds a query (not yet executed), the Arabic headings, the sheet title and
 * a mapper closure; row order is applied by the query builder.
 */
class ModuleExport implements FromQuery, WithHeadings, WithTitle
{
    /**
     * @param  array<int, string>  $headings
     * @param  Closure(object): array<int, mixed>  $mapper
     */
    public function __construct(
        private readonly Builder $query,
        private readonly array $headings,
        private readonly string $title,
        private readonly Closure $mapper,
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function map(mixed $row): array
    {
        return ($this->mapper)($row);
    }
}
