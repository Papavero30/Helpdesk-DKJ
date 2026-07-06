<?php

namespace App\Application\Services;

use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Lokasi;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Bulk-creates employee (karyawan) accounts from a mailbox export.
 *
 * Columns used (WithHeadingRow keys): display_name -> nama,
 * user_principal_name -> email, title -> Jabatan, department -> Divisi,
 * office -> Plant/Lokasi. Jabatan/Divisi/Plant are matched (token-based,
 * case-insensitive, longest wins) against EXISTING reference rows only; no
 * reference data is ever created. Unmatched values stay null.
 *
 * Parsing xlsx into rows happens separately (App\Imports\PersonImport) so this
 * service stays free of the zip/spreadsheet dependency and is unit-testable.
 */
class PersonImportService
{
    /** Default password for imported accounts; users must change it later. */
    public const DEFAULT_PASSWORD = 'dkj12345';

    /**
     * DRY RUN: resolve each row to matched reference ids + a status, WITHOUT
     * writing to the database. Feeds the preview screen.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function resolveRows(Collection $rows): array
    {
        $jabatanRefs = Jabatan::query()->where('is_active', true)->pluck('nama_jabatan', 'id')->all();
        $divisiRefs = Divisi::query()->where('is_active', true)->pluck('nama_divisi', 'id')->all();
        $lokasiRefs = Lokasi::query()->where('is_active', true)->pluck('nama_lokasi', 'id')->all();

        $existing = Karyawan::query()
            ->whereNotNull('email')
            ->pluck('email')
            ->mapWithKeys(fn ($e) => [mb_strtolower((string) $e) => true])
            ->all();

        $seen = [];
        $resolved = [];

        foreach ($rows->values() as $index => $row) {
            // Spreadsheet data starts at row 2 (row 1 is the heading).
            $rowNum = $index + 2;

            $nama = trim((string) ($row['display_name'] ?? ''));
            $email = trim((string) ($row['user_principal_name'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $dept = trim((string) ($row['department'] ?? ''));
            $office = trim((string) ($row['office'] ?? ''));

            $status = 'new';
            $error = null;

            if ($nama === '' || $email === '') {
                $status = 'invalid';
                $error = 'Nama atau Account (email) kosong.';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $status = 'invalid';
                $error = "Format email tidak valid: {$email}.";
            } else {
                $key = mb_strtolower($email);
                if (isset($existing[$key]) || isset($seen[$key])) {
                    $status = 'duplicate';
                } else {
                    $seen[$key] = true;
                }
            }

            $jab = $this->matchReference($title, $jabatanRefs);
            $div = $this->matchReference($dept, $divisiRefs);
            $lok = $this->matchReference($office, $lokasiRefs);

            $resolved[] = [
                'row' => $rowNum,
                'nama' => $nama,
                'email' => $email,
                'title' => $title,
                'department' => $dept,
                'office' => $office,
                'jabatan_id' => $jab['id'] ?? null,
                'jabatan_name' => $jab['name'] ?? null,
                'divisi_id' => $div['id'] ?? null,
                'divisi_name' => $div['name'] ?? null,
                'lokasi_id' => $lok['id'] ?? null,
                'lokasi_name' => $lok['name'] ?? null,
                'status' => $status,
                'error' => $error,
            ];
        }

        return $resolved;
    }

    /**
     * Persist the resolved rows: create karyawan + user for "new" rows only,
     * using the matched ids (or null). Skips duplicates/invalid. Re-checks
     * duplicates defensively in case the DB changed since the preview.
     *
     * @param  array<int, array<string, mixed>>  $resolved
     */
    public function commit(array $resolved): PersonImportResult
    {
        $result = new PersonImportResult;

        $existing = Karyawan::query()
            ->whereNotNull('email')
            ->pluck('email')
            ->mapWithKeys(fn ($e) => [mb_strtolower((string) $e) => true])
            ->all();

        $seen = [];

        DB::transaction(function () use ($resolved, $result, $existing, &$seen) {
            foreach ($resolved as $r) {
                $status = $r['status'] ?? 'new';

                if ($status === 'invalid') {
                    $result->invalid++;
                    if (! empty($r['error'])) {
                        $result->addError((int) ($r['row'] ?? 0), (string) $r['error']);
                    }

                    continue;
                }

                $email = trim((string) ($r['email'] ?? ''));
                $key = mb_strtolower($email);

                if ($status === 'duplicate' || $key === '' || isset($existing[$key]) || isset($seen[$key])) {
                    $result->skipped++;

                    continue;
                }
                $seen[$key] = true;

                $karyawan = Karyawan::create([
                    'nama' => (string) $r['nama'],
                    'email' => $email,
                    'no_telepon' => null,
                    'id_divisi' => $r['divisi_id'] ?? null,
                    'id_lokasi' => $r['lokasi_id'] ?? null,
                    'id_jabatan' => $r['jabatan_id'] ?? null,
                    'jabatan' => $r['jabatan_name'] ?? null,
                ]);

                User::create([
                    'id_karyawan' => $karyawan->id,
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'peran' => 'karyawan',
                    'status_akun' => 'active',
                ]);

                $result->created++;
            }
        });

        return $result;
    }

    /**
     * Token match a spreadsheet cell against reference rows.
     * A reference name matches when ALL of its word tokens appear as whole
     * words in the cell (case-insensitive). Among matches the one with the
     * most tokens wins (tie-break: longest name).
     *
     * @param  array<int, string>  $refs  id => name
     * @return array{id: int, name: string}|null
     */
    public function matchReference(?string $cell, array $refs): ?array
    {
        $cellTokens = $this->tokenize($cell);
        if ($cellTokens === []) {
            return null;
        }
        $cellSet = array_flip($cellTokens);

        $best = null;
        $bestScore = 0;
        $bestLen = 0;

        foreach ($refs as $id => $name) {
            $nameTokens = $this->tokenize($name);
            if ($nameTokens === []) {
                continue;
            }

            $allPresent = true;
            foreach ($nameTokens as $token) {
                if (! isset($cellSet[$token])) {
                    $allPresent = false;
                    break;
                }
            }
            if (! $allPresent) {
                continue;
            }

            $score = count($nameTokens);
            $len = mb_strlen((string) $name);
            if ($score > $bestScore || ($score === $bestScore && $len > $bestLen)) {
                $best = ['id' => (int) $id, 'name' => (string) $name];
                $bestScore = $score;
                $bestLen = $len;
            }
        }

        return $best;
    }

    /**
     * Lowercase and split into word tokens on any non-letter/non-digit.
     *
     * @return array<int, string>
     */
    private function tokenize(?string $value): array
    {
        $value = mb_strtolower(trim((string) $value));
        if ($value === '') {
            return [];
        }

        return preg_split('/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
