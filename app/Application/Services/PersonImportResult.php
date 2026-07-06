<?php

namespace App\Application\Services;

/**
 * Outcome of committing a person import: how many accounts were created,
 * skipped (duplicate emails), or invalid, plus any per-row problems.
 */
class PersonImportResult
{
    public int $created = 0;

    public int $skipped = 0;

    public int $invalid = 0;

    /** @var array<int, array{row: int, message: string}> */
    public array $errors = [];

    public function addError(int $row, string $message): void
    {
        $this->errors[] = ['row' => $row, 'message' => $message];
    }

    public function total(): int
    {
        return $this->created + $this->skipped + $this->invalid;
    }
}
