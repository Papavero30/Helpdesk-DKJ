<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class ReportTableExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    /**
     * @param  array  $rows  rows from ReportService::masterRows() (already filtered + sorted)
     * @param  string  $dimensionHeader  the dimension label (Admin / Category / Department / Plant)
     */
    public function __construct(
        private array $rows,
        private string $dimensionHeader,
    ) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return [
            $this->dimensionHeader,
            'Handled',
            'Resolved',
            'Resolution Rate %',
            'Repetitive',
            'Repetitive %',
            'Avg Resolution Time (h)',
            'On-Time Rate %',
            'Avg Rating',
        ];
    }

    /** Map one $rows entry → one Excel row matching the headings order. */
    public function map($row): array
    {
        return [
            $row['label'],
            $row['handled'],
            $row['resolved'],
            $row['resolution_rate'],
            $row['repetitive'],
            $row['repetitive_pct'],
            $row['avg_resolution_hours'] ?? '',
            $row['on_time_pct'],
            $row['avg_rating'] ?? '',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getStyle('A1:I1')->getFont()->setBold(true);
            },
        ];
    }
}
