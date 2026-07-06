<?php

namespace App\Livewire\Admin;

use App\Application\Services\ReportService;
use App\Exports\ReportTableExport;
use App\Models\User;
use App\Support\BreadcrumbStack;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app', ['title' => 'Report', 'description' => 'Global helpdesk performance report'])]
class Report extends Component
{
    #[Url(as: 'by', except: 'admin')]
    public string $groupBy = 'admin';

    /** Period as an inclusive Start–End date range (Y-m-d strings). */
    #[Url(as: 'start', except: '')]
    public string $startDate = '';

    #[Url(as: 'end', except: '')]
    public string $endDate = '';

    #[Url(as: 'admin', except: '')]
    public string $filterAdmin = '';

    #[Url(as: 'cat', except: '')]
    public string $filterCategory = '';

    #[Url(as: 'dept', except: '')]
    public string $filterDepartment = '';

    #[Url(as: 'plant', except: '')]
    public string $filterPlant = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: 'handled_desc')]
    public string $sortBy = 'handled_desc';

    // Detail drill-down modal state.
    public bool $showDetailModal = false;

    public ?string $detailLabel = null;

    public int $detailPage = 1;

    public const DETAIL_PER_PAGE = 10;

    public array $chartPayload = [];

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasAdminAccess()) {
            abort(403);
        }

        // Default range: first day of this month → today.
        if ($this->startDate === '') {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
        }
        if ($this->endDate === '') {
            $this->endDate = now()->format('Y-m-d');
        }

        BreadcrumbStack::push('Report', '/admin/report');
    }

    /** Switch the grouping dimension (Admin/Category/Department/Plant). */
    public function setGroupBy(string $value): void
    {
        if (in_array($value, ['admin', 'category', 'department', 'plant'], true)) {
            $this->groupBy = $value;
        }
    }

    /**
     * Set the range start date. Handled as an explicit action (wire:change →
     * here) rather than wire:model binding: native date pickers do not reliably
     * re-sync through Livewire's DOM morph, so a plain binding would fail to
     * update the view on the second pick. A non-date value is ignored.
     */
    public function updateStartDate(string $value): void
    {
        if ($this->isValidDate($value)) {
            $this->startDate = $value;
        }
    }

    /** Set the range end date. See updateStartDate() for why this is an action. */
    public function updateEndDate(string $value): void
    {
        if ($this->isValidDate($value)) {
            $this->endDate = $value;
        }
    }

    private function isValidDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value));
    }

    private function filters(): array
    {
        return [
            'groupBy' => $this->groupBy,
            'start' => $this->startDate,
            'end' => $this->endDate,
            'admin' => $this->filterAdmin,
            'category' => $this->filterCategory,
            'department' => $this->filterDepartment,
            'plant' => $this->filterPlant,
        ];
    }

    /**
     * Split a "<key>_<dir>" sort string on the LAST underscore so column keys
     * that themselves contain underscores (resolution_rate, on_time_pct,
     * avg_resolution_hours) parse correctly.
     *
     * @return array{0:string,1:string} [key, dir]
     */
    private function parseSortBy(string $sortBy): array
    {
        $pos = strrpos($sortBy, '_');
        if ($pos === false) {
            return [$sortBy, 'desc'];
        }
        $key = substr($sortBy, 0, $pos);
        $dir = substr($sortBy, $pos + 1);
        if (! in_array($dir, ['asc', 'desc'], true)) {
            return [$sortBy, 'desc'];
        }

        return [$key, $dir];
    }

    private function sortRows(array $rows): array
    {
        [$key, $dir] = $this->parseSortBy($this->sortBy);
        $allowed = ['label', 'handled', 'resolved', 'resolution_rate', 'repetitive', 'on_time_pct', 'avg_resolution_hours', 'avg_rating'];
        if (! in_array($key, $allowed, true)) {
            $key = 'handled';
        }

        usort($rows, function ($a, $b) use ($key, $dir) {
            $av = $a[$key] ?? 0;
            $bv = $b[$key] ?? 0;
            $cmp = is_string($av) ? strcasecmp($av, (string) $bv) : ($av <=> $bv);

            return $dir === 'asc' ? $cmp : -$cmp;
        });

        return $rows;
    }

    public function sortByColumn(string $key): void
    {
        [$currentKey, $currentDir] = $this->parseSortBy($this->sortBy);

        if ($currentKey === $key) {
            $this->sortBy = $key.'_'.($currentDir === 'desc' ? 'asc' : 'desc');
        } else {
            $this->sortBy = $key.'_desc';
        }
    }

    /** Open the per-row drill-down modal for the clicked dimension value. */
    public function openDetailModal(string $label): void
    {
        $this->detailLabel = $label;
        $this->detailPage = 1;
        $this->showDetailModal = true;
    }

    /** Close the drill-down modal and clear its state. */
    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->detailLabel = null;
        $this->detailPage = 1;
    }

    public function detailPageNext(): void
    {
        $this->detailPage++;
    }

    public function detailPagePrev(): void
    {
        $this->detailPage = max(1, $this->detailPage - 1);
    }

    public function detailGoToPage(int $page): void
    {
        $this->detailPage = max(1, $page);
    }

    /**
     * Download the currently-visible report table as .xlsx.
     * Honors all active filters / dimension / sort / search because $rows is
     * computed identically to render().
     */
    public function exportExcel(ReportService $reportService)
    {
        $filters = $this->filters();
        $rows = $reportService->masterRows($filters);

        if (trim($this->search) !== '') {
            $term = mb_strtolower(trim($this->search));
            $rows = array_values(array_filter($rows, fn ($r) => str_contains(mb_strtolower($r['label']), $term)));
        }
        $rows = $this->sortRows($rows);

        $dimensionHeader = match ($this->groupBy) {
            'category' => 'Category',
            'department' => 'Department',
            'plant' => 'Plant',
            default => 'Admin',
        };

        $filename = sprintf(
            'report-%s-%s-to-%s.xlsx',
            $this->groupBy,
            $this->startDate ?: 'all',
            $this->endDate ?: 'all',
        );

        return Excel::download(
            new ReportTableExport($rows, $dimensionHeader),
            $filename,
        );
    }

    public function render(ReportService $reportService)
    {
        abort_if(! Auth::user()?->hasAdminAccess(), 403);

        $filters = $this->filters();

        $rows = $reportService->masterRows($filters);

        // Search by row label (e.g. admin name). This narrows the TABLE rows (and
        // therefore the totals row, which is derived from $rows) but intentionally
        // does NOT affect the charts below — chartPayload() reflects the full
        // filtered dataset so charts stay stable while the user types in search.
        if (trim($this->search) !== '') {
            $term = mb_strtolower(trim($this->search));
            $rows = array_values(array_filter(
                $rows,
                fn ($r) => str_contains(mb_strtolower($r['label']), $term)
            ));
        }

        $rows = $this->sortRows($rows);

        $this->chartPayload = $reportService->chartPayload($filters);

        $detail = ['total' => 0, 'totalPages' => 0, 'page' => 1, 'tickets' => collect()];
        if ($this->showDetailModal && $this->detailLabel !== null) {
            $detail = $reportService->detailTickets(
                $filters,
                $this->groupBy,
                $this->detailLabel,
                $this->detailPage,
                self::DETAIL_PER_PAGE,
            );
            // Clamp current page if total shrank (e.g. filter changed underneath).
            // Only clamp when there are actual results; zero totalPages means the
            // dimension value was not found, so leave detailPage untouched.
            if ($detail['totalPages'] > 0) {
                $this->detailPage = $detail['page'];
            }
        }

        return view('livewire.admin.report', [
            'rows' => $rows,
            'totals' => $reportService->totals($filters, $rows),
            'options' => $reportService->filterOptions(),
            'detail' => $detail,
        ]);
    }
}
