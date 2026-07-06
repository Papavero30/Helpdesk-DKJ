<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Parses the mailbox export xlsx into a normalized collection of rows.
 *
 * WithHeadingRow maps the header cells to snake_case keys, e.g.
 * "Display name" -> display_name, "User principal name" -> user_principal_name,
 * "Title" -> title, "Department" -> department, "Office" -> office.
 *
 * The export contains two sheets: the data sheet (index 0) plus a trailing
 * blank "Sheet1". WithMultipleSheets restricts the read to the first sheet so
 * the empty second sheet does not overwrite the parsed rows. Business logic
 * lives in PersonImportService.
 */
class PersonImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    /** @var Collection<int, array<string, mixed>> */
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    /**
     * Read only the first sheet (the exported people).
     *
     * @return array<int, ToCollection>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
