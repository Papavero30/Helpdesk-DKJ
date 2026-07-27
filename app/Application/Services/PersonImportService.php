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
     * Ejaan berbeda yang harus dianggap satu kata: hasil terjemahan
     * (Produksi = Production) dan singkatan (RND = R&D). Dipakai pada kedua sisi,
     * baik sel spreadsheet maupun nama master data, sehingga keduanya bertemu di
     * bentuk kanonik yang sama. Salah ketik biasa tidak perlu dicantumkan di sini
     * karena sudah tertangkap toleransi jarak edit.
     */
    private const TOKEN_ALIASES = [
        'logistik' => 'logistic',
        'produksi' => 'production',
        'keuangan' => 'finance',
        'pemasaran' => 'marketing',
        'pembelian' => 'purchasing',
        'pemeliharaan' => 'maintenance',
        'perawatan' => 'maintenance',
        'tekhnisi' => 'teknisi',
        'rnd' => 'rd',   // "R&D" menjadi satu kata "rd" setelah '&' dibuang
        'hcga' => 'hrga',
    ];

    /** Kemiripan minimal untuk pencocokan seluruh kalimat (cadangan terakhir). */
    private const PHRASE_SIMILARITY = 0.85;

    /**
     * Cocokkan satu sel spreadsheet dengan daftar master data.
     *
     * Sebuah nama master data cocok bila SEMUA katanya muncul di dalam sel, dengan
     * toleransi salah ketik: makin panjang kata, makin banyak huruf salah yang
     * dimaafkan (sampai 3 huruf harus persis, 4 sampai 6 huruf boleh salah 1,
     * lebih panjang boleh salah 2). Pemenangnya yang katanya paling banyak; bila
     * seri, yang paling sedikit salah ketiknya, lalu yang namanya paling panjang.
     *
     * Bila tidak ada yang cocok per kata, dicoba kemiripan seluruh kalimat.
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

        $best = null;
        $bestScore = 0;
        $bestDistance = PHP_INT_MAX;
        $bestLen = 0;

        foreach ($refs as $id => $name) {
            $nameTokens = $this->tokenize($name);
            if ($nameTokens === []) {
                continue;
            }

            $distance = 0;
            $allPresent = true;
            foreach ($nameTokens as $token) {
                $closest = $this->closestDistance($token, $cellTokens);
                if ($closest === null) {
                    $allPresent = false;
                    break;
                }
                $distance += $closest;
            }
            if (! $allPresent) {
                continue;
            }

            $score = count($nameTokens);
            $len = mb_strlen((string) $name);
            $better = $score > $bestScore
                || ($score === $bestScore && $distance < $bestDistance)
                || ($score === $bestScore && $distance === $bestDistance && $len > $bestLen);

            if ($better) {
                $best = ['id' => (int) $id, 'name' => (string) $name];
                $bestScore = $score;
                $bestDistance = $distance;
                $bestLen = $len;
            }
        }

        return $best ?? $this->matchWholePhrase($cellTokens, $refs);
    }

    /**
     * Jarak terkecil antara satu kata master data dan kata-kata di dalam sel,
     * atau null bila tidak ada yang cukup mirip.
     *
     * @param  array<int, string>  $cellTokens
     */
    private function closestDistance(string $token, array $cellTokens): ?int
    {
        $best = null;

        foreach ($cellTokens as $cellToken) {
            if ($cellToken === $token) {
                return 0;
            }

            $limit = $this->maxDistance(max(strlen($token), strlen($cellToken)));
            if ($limit === 0 || strlen($token) > 255 || strlen($cellToken) > 255) {
                continue;
            }

            $distance = levenshtein($token, $cellToken);
            if ($distance <= $limit && ($best === null || $distance < $best)) {
                $best = $distance;
            }
        }

        return $best;
    }

    /** Berapa huruf salah yang dimaafkan untuk kata sepanjang $len. */
    private function maxDistance(int $len): int
    {
        if ($len <= 3) {
            return 0;
        }

        return $len <= 6 ? 1 : 2;
    }

    /**
     * Cadangan terakhir: bandingkan seluruh kalimat yang sudah dinormalkan.
     * Hanya menolong bila panjang keduanya berdekatan, jadi aman dari salah tebak.
     *
     * @param  array<int, string>  $cellTokens
     * @param  array<int, string>  $refs
     * @return array{id: int, name: string}|null
     */
    private function matchWholePhrase(array $cellTokens, array $refs): ?array
    {
        $cell = implode(' ', $cellTokens);
        if ($cell === '' || strlen($cell) > 255) {
            return null;
        }

        $best = null;
        $bestSimilarity = 0.0;

        foreach ($refs as $id => $name) {
            $ref = implode(' ', $this->tokenize($name));
            if ($ref === '' || strlen($ref) > 255) {
                continue;
            }

            $longest = max(strlen($cell), strlen($ref));
            $similarity = 1 - (levenshtein($cell, $ref) / $longest);

            if ($similarity >= self::PHRASE_SIMILARITY && $similarity > $bestSimilarity) {
                $best = ['id' => (int) $id, 'name' => (string) $name];
                $bestSimilarity = $similarity;
            }
        }

        return $best;
    }

    /**
     * Huruf kecil, buang '&' agar "R&D" jadi satu kata "rd" sementara
     * "Sales & Marketing" tetap dua kata, lalu pecah pada tiap karakter yang bukan
     * huruf/angka. Terakhir tiap kata dipetakan ke bentuk kanoniknya.
     *
     * @return array<int, string>
     */
    private function tokenize(?string $value): array
    {
        $value = mb_strtolower(trim((string) $value));
        if ($value === '') {
            return [];
        }

        $value = str_replace('&', '', $value);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $t) => self::TOKEN_ALIASES[$t] ?? $t, $tokens);
    }
}
