<?php

namespace Database\Seeders;

use App\Application\Services\TiketService;
use App\Models\ActivityLog;
use App\Models\Divisi;
use App\Models\GrupTiket;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Kategori;
use App\Models\KomentarTicket;
use App\Models\Lokasi;
use App\Models\Penilaian;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $kategoris = $this->seedKategori();
        $statuses = $this->seedStatusTiket();
        $lokasi = $this->seedLokasi();
        $divisi = $this->seedDivisi();

        [$admins, $karyawanUsers] = $this->seedUsersAndKaryawan($lokasi, $divisi);

        $this->linkJabatan();

        // Skip ticket seeding in the test environment so feature tests can assert
        // exact counts without interference from demo data.
        if (app()->environment('testing')) {
            return;
        }

        $this->seedTiket($kategoris, $statuses, $lokasi, $admins, $karyawanUsers);
        $this->seedDemoTickets($kategoris, $statuses, $lokasi, $admins, $karyawanUsers);
        $this->seedGrupTiket();
        $this->seedRepetitiveNegotiations();
    }

    /**
     * Simulate the repetitive OFF negotiation flow via the real TiketService so
     * the Activity Log "Repetitive" tab has realistic audit entries for demo:
     *  1. request -> user accept           (Repetitive -> Not Repetitive)
     *  2. request -> user refuse -> admin concede (Repetitive -> Repetitive)
     *  3. request -> auto-accept timeout   (Repetitive -> Not Repetitive)
     *  4. request only (pending review)    (Repetitive -> Pending Review)
     */
    private function seedRepetitiveNegotiations(): void
    {
        $tiketService = app(TiketService::class);

        $inProgressId = StatusTiketModel::findByName('In Progress')?->id;
        if (! $inProgressId) {
            return;
        }

        $eligible = Tiket::query()
            ->where('berulang', true)
            ->where('id_status_tiket', $inProgressId)
            ->where('siap_konfirmasi', false)
            ->where('repetitive_review_state', 'none')
            ->whereNotNull('id_penanggung_jawab')
            ->orderBy('id')
            ->get();

        if ($eligible->isEmpty()) {
            return;
        }

        $scenarios = ['accept', 'refuse_concede', 'auto_accept', 'pending'];

        foreach ($eligible as $idx => $tiket) {
            $scenario = $scenarios[$idx % count($scenarios)];
            $adminId = $tiket->id_penanggung_jawab;
            $userId = $tiket->id_pengguna;

            try {
                match ($scenario) {
                    'accept' => (function () use ($tiketService, $tiket, $adminId, $userId) {
                        $tiketService->requestRepetitiveOff($tiket, $adminId, 'This issue has a different root cause from previous occurrences.');
                        $tiketService->acceptRepetitiveOff($tiket->fresh(), $userId);
                    })(),
                    'refuse_concede' => (function () use ($tiketService, $tiket, $adminId, $userId) {
                        $tiketService->requestRepetitiveOff($tiket, $adminId, 'I believe this is a one-off, not a recurring problem.');
                        $tiketService->refuseRepetitiveOff($tiket->fresh(), $userId, 'This is the third time the same printer fails the same way.');
                        $tiketService->acceptUserRefusal($tiket->fresh(), $adminId);
                    })(),
                    'auto_accept' => (function () use ($tiketService, $tiket, $adminId) {
                        $tiketService->requestRepetitiveOff($tiket, $adminId, 'Requesting removal — awaiting user confirmation.');
                        $fresh = $tiket->fresh();
                        // Backdate so the auto-accept threshold (4h) is exceeded
                        $fresh->update([
                            'repetitive_review_admin_at' => now()->subHours(6),
                            'sla_paused_at' => now()->subHours(6),
                        ]);
                        $tiketService->autoAcceptStaleRequest($fresh->fresh());
                    })(),
                    'pending' => $tiketService->requestRepetitiveOff($tiket, $adminId, 'Please review — I think this ticket is not actually repetitive.'),
                };
            } catch (\Throwable $e) {
                // Skip ticket on any domain guard failure; keep seeding resilient
                continue;
            }
        }
    }

    private function seedKategori()
    {
        $kategoriData = [
            ['nama_kategori' => 'Troubleshooting', 'batas_jam_sla' => 24, 'urgensi' => 1, 'warna_grafik' => '#0E4260'],
            ['nama_kategori' => 'Security',        'batas_jam_sla' => 12, 'urgensi' => 2, 'warna_grafik' => '#dc2626'],
            ['nama_kategori' => 'CCTV',            'batas_jam_sla' => 48, 'urgensi' => 3, 'warna_grafik' => '#f59e0b'],
            ['nama_kategori' => 'IT Project',      'batas_jam_sla' => 72, 'urgensi' => 4, 'warna_grafik' => '#3b82f6'],
            ['nama_kategori' => 'Other',           'batas_jam_sla' => 48, 'urgensi' => 5, 'warna_grafik' => '#6b7280'],
        ];

        return collect($kategoriData)
            ->map(fn (array $kategori) => Kategori::updateOrCreate(
                ['nama_kategori' => $kategori['nama_kategori']],
                $kategori,
            ))
            ->keyBy('nama_kategori');
    }

    private function seedStatusTiket(): array
    {
        return [
            'open' => StatusTiketModel::firstOrCreate(['nama_status' => 'Open']),
            'in_progress' => StatusTiketModel::firstOrCreate(['nama_status' => 'In Progress']),
            'close' => StatusTiketModel::firstOrCreate(['nama_status' => 'Close']),
        ];
    }

    private function seedLokasi()
    {
        $locationNames = ['Gresik', 'Cibitung', 'Jakarta', 'Bandung', 'Surabaya', 'Semarang'];

        return collect($locationNames)
            ->map(fn (string $namaLokasi) => Lokasi::firstOrCreate(['nama_lokasi' => $namaLokasi]))
            ->values();
    }

    private function seedDivisi()
    {
        $divisiNames = ['Keuangan', 'SDM', 'Pemasaran', 'Produksi', 'Logistik', 'R&D', 'Legal', 'Umum'];

        return collect($divisiNames)
            ->map(fn (string $namaDivisi) => Divisi::firstOrCreate(['nama_divisi' => $namaDivisi]))
            ->values();
    }

    private function linkJabatan(): void
    {
        foreach (Karyawan::whereNotNull('jabatan')->get() as $k) {
            $jab = Jabatan::firstOrCreate(['nama_jabatan' => $k->jabatan], ['is_active' => true]);
            if ($k->id_jabatan !== $jab->id) {
                $k->update(['id_jabatan' => $jab->id]);
            }
        }
    }

    private function seedUsersAndKaryawan($lokasi, $divisi): array
    {
        $adminData = [
            ['nama' => 'Admin Gresik',   'email' => 'admin.gresik@ithelp.local',   'lokasi_idx' => 0],
            ['nama' => 'Admin Cibitung', 'email' => 'admin.cibitung@ithelp.local', 'lokasi_idx' => 1],
            ['nama' => 'Admin Jakarta',  'email' => 'admin@ithelp.local',          'lokasi_idx' => 2],
        ];

        $admins = collect();
        foreach ($adminData as $data) {
            $karyawan = Karyawan::updateOrCreate(
                ['email' => $data['email']],
                [
                    'nama' => $data['nama'],
                    'email' => $data['email'],
                    'no_telepon' => '08'.str_pad((string) random_int(1000000000, 9999999999), 10, '0', STR_PAD_LEFT),
                    'id_divisi' => $divisi[0]->id,
                    'id_lokasi' => $lokasi[$data['lokasi_idx']]->id,
                    'jabatan' => 'Admin IT',
                ],
            );

            $adminUser = User::updateOrCreate(
                ['id_karyawan' => $karyawan->id],
                [
                    'id_karyawan' => $karyawan->id,
                    'password' => bcrypt('admin123'),
                    'peran' => 'admin',
                    'status_akun' => 'active',
                ],
            );

            $admins->put($data['email'], $adminUser);
        }

        // Manager (read-only admin access) — dev login: manager@ithelp.local / manager123
        $managerKaryawan = Karyawan::updateOrCreate(
            ['email' => 'manager@ithelp.local'],
            [
                'nama' => 'Manager IT',
                'email' => 'manager@ithelp.local',
                'no_telepon' => '0800000001',
                'id_divisi' => $divisi[0]->id,
                'id_lokasi' => $lokasi[0]->id,
                'jabatan' => 'IT Manager',
            ],
        );

        User::updateOrCreate(
            ['id_karyawan' => $managerKaryawan->id],
            [
                'id_karyawan' => $managerKaryawan->id,
                'password' => bcrypt('manager123'),
                'peran' => 'manager',
                'status_akun' => 'active',
            ],
        );

        $karyawanData = [
            ['nama' => 'Andi Prasetyo',  'no_telepon' => '081234567001', 'email' => 'andi@perusahaan.com',   'jabatan' => 'Staff IT'],
            ['nama' => 'Siti Nurhaliza', 'no_telepon' => '081234567002', 'email' => 'siti@perusahaan.com',   'jabatan' => 'Staff HR'],
            ['nama' => 'Budi Santoso',   'no_telepon' => '081234567003', 'email' => 'budi@perusahaan.com',   'jabatan' => 'Supervisor'],
            ['nama' => 'Dewi Lestari',   'no_telepon' => '081234567004', 'email' => 'dewi@perusahaan.com',   'jabatan' => 'Staff Marketing'],
            ['nama' => 'Rudi Hermawan',  'no_telepon' => '081234567005', 'email' => 'rudi@perusahaan.com',   'jabatan' => 'Staff Produksi'],
            ['nama' => 'Putri Ayu',      'no_telepon' => '081234567006', 'email' => 'putri@perusahaan.com',  'jabatan' => 'Staff Keuangan'],
            ['nama' => 'Hendra Wijaya',  'no_telepon' => '081234567007', 'email' => 'hendra@perusahaan.com', 'jabatan' => 'Manager'],
            ['nama' => 'Maya Sari',      'no_telepon' => '081234567008', 'email' => 'maya@perusahaan.com',   'jabatan' => 'Staff Logistik'],
            ['nama' => 'Agus Setiawan',  'no_telepon' => '081234567009', 'email' => 'agus@perusahaan.com',   'jabatan' => 'Staff IT'],
            ['nama' => 'Ratna Dewi',     'no_telepon' => '081234567010', 'email' => 'ratna@perusahaan.com',  'jabatan' => 'Staff HR'],
            ['nama' => 'Faisal Rahman',  'no_telepon' => '081234567011', 'email' => 'faisal@perusahaan.com', 'jabatan' => 'Staff R&D'],
            ['nama' => 'Indah Permata',  'no_telepon' => '081234567012', 'email' => 'indah@perusahaan.com',  'jabatan' => 'Staff Legal'],
            ['nama' => 'Rizki Pratama',  'no_telepon' => '081234567013', 'email' => 'rizki@perusahaan.com',  'jabatan' => 'Staff Umum'],
            ['nama' => 'Nurul Hidayah',  'no_telepon' => '081234567014', 'email' => 'nurul@perusahaan.com',  'jabatan' => 'Staff Produksi'],
            ['nama' => 'Dimas Ardianto', 'no_telepon' => '081234567015', 'email' => 'dimas@perusahaan.com',  'jabatan' => 'Staff Marketing'],
        ];

        $karyawanUsers = collect();
        foreach ($karyawanData as $index => $data) {
            $karyawan = Karyawan::updateOrCreate(
                ['email' => $data['email']],
                [
                    'nama' => $data['nama'],
                    'email' => $data['email'],
                    'no_telepon' => $data['no_telepon'],
                    'id_divisi' => $divisi[$index % $divisi->count()]->id,
                    'id_lokasi' => $lokasi[$index % $lokasi->count()]->id,
                    'jabatan' => $data['jabatan'],
                ],
            );

            $user = User::updateOrCreate(
                ['id_karyawan' => $karyawan->id],
                [
                    'id_karyawan' => $karyawan->id,
                    'password' => bcrypt('karyawan123'),
                    'peran' => 'karyawan',
                    'status_akun' => 'active',
                ],
            );

            $karyawanUsers->put($data['email'], $user);
        }

        return [$admins, $karyawanUsers];
    }

    /**
     * Seed a small set of "everyday" tickets across multiple users (3 Open, 2 In Progress, 1 Close).
     */
    private function seedTiket($kategoris, array $statuses, $lokasi, $admins, $karyawanUsers): void
    {
        $ticketData = [
            ['user_email' => 'andi@perusahaan.com',  'lokasi' => 0, 'kategori' => 'Troubleshooting', 'deskripsi' => 'Koneksi internet di ruangan saya putus-putus sejak tadi pagi.',                       'status' => 'open'],
            ['user_email' => 'dewi@perusahaan.com',  'lokasi' => 2, 'kategori' => 'Security',        'deskripsi' => 'Muncul pop-up mencurigakan di komputer saat membuka browser.',                          'status' => 'open'],
            ['user_email' => 'putri@perusahaan.com', 'lokasi' => 1, 'kategori' => 'IT Project',      'deskripsi' => 'Request setup workstation baru untuk tim marketing.',                                  'status' => 'open'],
            ['user_email' => 'siti@perusahaan.com',  'lokasi' => 0, 'kategori' => 'CCTV',            'deskripsi' => 'CCTV di gudang utama mati total, tidak ada rekaman sejak kemarin.',                    'status' => 'in_progress', 'admin_email' => 'admin.gresik@ithelp.local'],
            ['user_email' => 'budi@perusahaan.com',  'lokasi' => 0, 'kategori' => 'Troubleshooting', 'deskripsi' => 'Monitor kedua tidak terdeteksi setelah update Windows.',                              'status' => 'in_progress', 'admin_email' => 'admin.gresik@ithelp.local'],
            ['user_email' => 'rudi@perusahaan.com',  'lokasi' => 2, 'kategori' => 'Other',           'deskripsi' => 'Perlu reset password email kantor.',                                                  'status' => 'close',       'admin_email' => 'admin@ithelp.local',         'sla_outcome' => 'on_time', 'score' => 5, 'rating_komentar' => 'Cepat dan tuntas, terima kasih.'],
        ];

        $komentarTemplates = [
            'Terima kasih laporannya. Kami sedang mengecek.',
            'Sudah kami proses. Mohon dicoba kembali.',
            'Teknisi sedang menuju lokasi.',
            'Masalah sudah teridentifikasi. Sedang dalam perbaikan.',
            'Sudah selesai diperbaiki. Mohon konfirmasi.',
        ];

        foreach ($ticketData as $data) {
            $user = $karyawanUsers->get($data['user_email']);
            $admin = isset($data['admin_email']) ? $admins->get($data['admin_email']) : null;
            $kategori = $kategoris[$data['kategori']];
            $statusKey = $data['status'];
            $status = $statuses[$statusKey];
            $createdAt = now()->subDays(rand(1, 14))->subHours(rand(1, 12));

            $assignedAt = $admin ? $createdAt->copy()->addMinutes(rand(30, 240)) : null;
            $target = $assignedAt ? $assignedAt->copy()->addHours($kategori->batas_jam_sla) : null;

            $closedAt = null;
            $slaOutcome = null;
            if ($statusKey === 'close' && $assignedAt) {
                $slaOutcome = $data['sla_outcome'] ?? 'on_time';
                $closedAt = match ($slaOutcome) {
                    'overtime' => $target->copy()->addHours(rand(2, 24)),
                    'ahead' => $assignedAt->copy()->addHours(max(1, intval($kategori->batas_jam_sla / 2) - rand(1, 3))),
                    default => $assignedAt->copy()->addHours(max(1, $kategori->batas_jam_sla - rand(1, 4))),
                };
            }

            $tiket = Tiket::create([
                'id_pengguna' => $user->id,
                'id_lokasi' => $lokasi[$data['lokasi']]->id,
                'id_kategori' => $kategori->id,
                'deskripsi' => $data['deskripsi'],
                'id_status_tiket' => $status->id,
                'id_penanggung_jawab' => $admin?->id,
                'berulang' => false,
                'target_penyelesaian' => $target,
                'waktu_selesai' => $closedAt,
                'ditutup_pada' => $closedAt,
                'siap_konfirmasi' => false,
                'sla_outcome' => $slaOutcome,
                'created_at' => $createdAt,
                'updated_at' => $closedAt ?? $assignedAt ?? $createdAt,
            ]);

            // ticket_created
            ActivityLog::create([
                'tiket_id' => $tiket->id,
                'id_pengguna' => $user->id,
                'aksi' => 'ticket_created',
                'status_baru' => 'Open',
                'keterangan' => "New ticket created — category: {$data['kategori']}",
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if ($admin) {
                ActivityLog::create([
                    'tiket_id' => $tiket->id,
                    'id_pengguna' => $admin->id,
                    'aksi' => 'ticket_assigned',
                    'status_lama' => 'Open',
                    'status_baru' => 'In Progress',
                    'keterangan' => 'Admin took the ticket',
                    'created_at' => $assignedAt,
                    'updated_at' => $assignedAt,
                ]);

                $numKomentar = $statusKey === 'close' ? rand(2, 3) : rand(1, 2);
                for ($i = 0; $i < $numKomentar; $i++) {
                    $commentAt = $assignedAt->copy()->addHours(rand(1, 8) * ($i + 1));
                    if ($closedAt && $commentAt > $closedAt) {
                        $commentAt = $closedAt->copy()->subMinutes(rand(15, 60));
                    }

                    $isiKomentar = $komentarTemplates[array_rand($komentarTemplates)];
                    KomentarTicket::create([
                        'id_tiket' => $tiket->id,
                        'id_pengirim' => $admin->id,
                        'isi_komentar' => $isiKomentar,
                        'created_at' => $commentAt,
                        'updated_at' => $commentAt,
                    ]);

                    ActivityLog::create([
                        'tiket_id' => $tiket->id,
                        'id_pengguna' => $admin->id,
                        'aksi' => 'reply_posted',
                        'keterangan' => Str::limit(trim($isiKomentar), 200),
                        'created_at' => $commentAt,
                        'updated_at' => $commentAt,
                    ]);
                }

                if ($statusKey === 'close' && $closedAt) {
                    ActivityLog::create([
                        'tiket_id' => $tiket->id,
                        'id_pengguna' => $admin->id,
                        'aksi' => 'status_changed',
                        'status_lama' => 'In Progress',
                        'status_baru' => 'In Progress (Awaiting Confirmation)',
                        'keterangan' => 'Admin marked ticket as resolved — awaiting employee confirmation',
                        'created_at' => $closedAt->copy()->subMinutes(rand(30, 120)),
                        'updated_at' => $closedAt->copy()->subMinutes(rand(30, 120)),
                    ]);

                    ActivityLog::create([
                        'tiket_id' => $tiket->id,
                        'id_pengguna' => $user->id,
                        'aksi' => 'feedback_solved',
                        'keterangan' => 'User confirmed the issue is resolved',
                        'created_at' => $closedAt,
                        'updated_at' => $closedAt,
                    ]);

                    ActivityLog::create([
                        'tiket_id' => $tiket->id,
                        'id_pengguna' => null,
                        'aksi' => 'status_changed',
                        'status_lama' => 'In Progress',
                        'status_baru' => 'Close',
                        'keterangan' => 'Ticket closed after employee confirmation',
                        'created_at' => $closedAt,
                        'updated_at' => $closedAt,
                    ]);

                    if (isset($data['score'])) {
                        Penilaian::create([
                            'id_tiket' => $tiket->id,
                            'nilai' => $data['score'],
                            'komentar' => $data['rating_komentar'] ?? null,
                            'created_at' => $closedAt->copy()->addMinutes(rand(10, 180)),
                            'updated_at' => $closedAt->copy()->addMinutes(rand(10, 180)),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Seed a richer set of demo tickets owned by a single user (andi@) so dashboards have variety.
     * Distribution: 5 Open, 7 In Progress, 18 Close (mixed SLA outcomes + ratings).
     */
    private function seedDemoTickets($kategoris, array $statuses, $lokasi, $admins, $karyawanUsers): void
    {
        $demoUser = $karyawanUsers->get('andi@perusahaan.com');
        if (! $demoUser) {
            return;
        }

        $distribution = $this->buildDemoDistribution();

        foreach ($distribution as $data) {
            $this->createDemoTicket($demoUser, $data, $kategoris, $statuses, $lokasi, $admins);
        }
    }

    private function buildDemoDistribution(): array
    {
        $descriptions = [
            'Troubleshooting' => [
                'Laptop tidak bisa connect WiFi di area kerja saya.',
                'Printer di meja saya tiba-tiba tidak responsif.',
                'Excel sering crash saat buka file budget bulanan.',
                'Monitor berkedip-kedip setelah update driver.',
                'Mouse wireless tidak merespon lagi, ganti baterai juga tetap.',
                'Laptop panas berlebihan dan sering mati sendiri.',
                'Outlook tidak bisa send email sejak pagi, error 0x800.',
                'Dual monitor setup tidak detected, hanya 1 yang jalan.',
                'Print queue stuck, tidak bisa print dokumen penting.',
                'Komputer lambat sekali saat buka folder network share.',
                'Webcam di laptop tidak terdeteksi saat meeting Zoom.',
                'WiFi sering disconnect di ruang meeting lantai 3.',
            ],
            'Security' => [
                'Ada pop-up mencurigakan saat buka situs kerja.',
                'Email phishing masuk ke inbox, mohon investigasi.',
                'Akun AD saya terkunci, tidak bisa login ke domain.',
                'USB drive vendor ditolak antivirus, perlu clearance.',
                'Ada notifikasi login dari IP yang tidak dikenal.',
            ],
            'CCTV' => [
                'CCTV gudang bahan baku mati total sejak tadi malam.',
                'Rekaman CCTV parkiran blur, perlu kalibrasi ulang.',
                'DVR room kontrol tidak bisa akses, monitor black.',
                'CCTV lobby depan tidak merekam sejak kemarin.',
            ],
            'IT Project' => [
                'Request setup workstation baru untuk staff magang.',
                'Instalasi Microsoft Office 365 di 5 komputer tim.',
                'Migrasi data server lama ke server baru sebelum akhir bulan.',
                'Setup VPN untuk tim yang kerja remote minggu depan.',
                'Request domain email baru untuk divisi marketing.',
                'Instalasi software accounting di workstation finance.',
            ],
            'Other' => [
                'Perlu reset password email kantor, lupa.',
                'Request akses shared folder tim marketing.',
                'Tolong bantu recover file yang terhapus dari recycle bin.',
                'Minta konfigurasi signature email standard perusahaan.',
                'Request akses VPN untuk business trip ke Jakarta.',
                'Permintaan mapping network drive tim logistik.',
                'Tolong setup auto-reply email untuk cuti minggu depan.',
                'Minta install aplikasi Zoom versi enterprise.',
            ],
        ];

        $idx = ['Troubleshooting' => 0, 'Security' => 0, 'CCTV' => 0, 'IT Project' => 0, 'Other' => 0];

        $nextDesc = function (string $cat) use (&$descriptions, &$idx): string {
            $list = $descriptions[$cat];
            $text = $list[$idx[$cat] % count($list)];
            $idx[$cat]++;

            return $text;
        };

        $dist = [];

        // 5 Open
        $openSpecs = [
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 0, 'days_ago' => 2],
            ['kategori' => 'Security',        'lokasi_idx' => 0, 'days_ago' => 1],
            ['kategori' => 'Other',           'lokasi_idx' => 1, 'days_ago' => 3],
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 0, 'days_ago' => 5],
            ['kategori' => 'IT Project',      'lokasi_idx' => 0, 'days_ago' => 4],
        ];
        foreach ($openSpecs as $s) {
            $dist[] = [
                'lokasi_idx' => $s['lokasi_idx'],
                'kategori' => $s['kategori'],
                'deskripsi' => $nextDesc($s['kategori']),
                'status' => 'open',
                'admin_email' => null,
                'days_ago' => $s['days_ago'],
                'hours_offset' => rand(1, 20),
                'sla_outcome' => null,
                'score' => null,
                'num_komentar' => 0,
            ];
        }

        // 7 In Progress
        $inProgressSpecs = [
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 6,  'num_komentar' => 3],
            ['kategori' => 'CCTV',            'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 8,  'num_komentar' => 4],
            ['kategori' => 'IT Project',      'lokasi_idx' => 1, 'admin' => 'admin.cibitung@ithelp.local', 'days_ago' => 10, 'num_komentar' => 3],
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 12, 'num_komentar' => 5],
            ['kategori' => 'Other',           'lokasi_idx' => 2, 'admin' => 'admin@ithelp.local',          'days_ago' => 4,  'num_komentar' => 3],
            ['kategori' => 'Security',        'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 7,  'num_komentar' => 4],
            ['kategori' => 'IT Project',      'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 9,  'num_komentar' => 3],
        ];
        foreach ($inProgressSpecs as $s) {
            $dist[] = [
                'lokasi_idx' => $s['lokasi_idx'],
                'kategori' => $s['kategori'],
                'deskripsi' => $nextDesc($s['kategori']),
                'status' => 'in_progress',
                'admin_email' => $s['admin'],
                'days_ago' => $s['days_ago'],
                'hours_offset' => rand(1, 20),
                'sla_outcome' => null,
                'score' => null,
                'num_komentar' => $s['num_komentar'],
            ];
        }

        // 18 Close: 11 on_time, 4 ahead, 3 overtime — ratings spread 1-5
        $closeSpecs = [
            // on_time (11)
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 55, 'sla' => 'on_time',  'score' => 5, 'rating_komentar' => 'Sangat membantu, masalah teratasi cepat.'],
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 50, 'sla' => 'on_time',  'score' => 4, 'rating_komentar' => 'Cukup memuaskan, sesuai harapan.'],
            ['kategori' => 'Security',        'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 48, 'sla' => 'on_time',  'score' => 5, 'rating_komentar' => 'Respon cepat, masalah keamanan langsung diatasi.'],
            ['kategori' => 'CCTV',            'lokasi_idx' => 1, 'admin' => 'admin.cibitung@ithelp.local', 'days_ago' => 45, 'sla' => 'on_time',  'score' => 4, 'rating_komentar' => 'Bagus, CCTV sudah jalan normal.'],
            ['kategori' => 'IT Project',      'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 42, 'sla' => 'on_time',  'score' => 3, 'rating_komentar' => 'Cukup baik, tapi bisa lebih cepat.'],
            ['kategori' => 'Other',           'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 40, 'sla' => 'on_time',  'score' => 5, 'rating_komentar' => 'Pelayanan profesional dan solutif.'],
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 38, 'sla' => 'on_time',  'score' => 4, 'rating_komentar' => 'Solusi tepat sasaran.'],
            ['kategori' => 'Security',        'lokasi_idx' => 2, 'admin' => 'admin@ithelp.local',          'days_ago' => 35, 'sla' => 'on_time',  'score' => 4, 'rating_komentar' => 'Mantap, akun sudah unlocked.'],
            ['kategori' => 'Other',           'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 33, 'sla' => 'on_time',  'score' => 3, 'rating_komentar' => 'Lumayan, tapi komunikasi bisa lebih jelas.'],
            ['kategori' => 'IT Project',      'lokasi_idx' => 1, 'admin' => 'admin.cibitung@ithelp.local', 'days_ago' => 30, 'sla' => 'on_time',  'score' => 4, 'rating_komentar' => 'Setup workstation rapi.'],
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 27, 'sla' => 'on_time',  'score' => 5, 'rating_komentar' => 'Excellent! Masalah selesai dalam beberapa jam.'],

            // ahead (4)
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 24, 'sla' => 'ahead',    'score' => 5, 'rating_komentar' => 'Cepat banget, di luar dugaan!'],
            ['kategori' => 'CCTV',            'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 20, 'sla' => 'ahead',    'score' => 5, 'rating_komentar' => 'Selesai sebelum deadline, top!'],
            ['kategori' => 'Security',        'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 16, 'sla' => 'ahead',    'score' => 4, 'rating_komentar' => 'Lebih cepat dari ekspektasi.'],
            ['kategori' => 'Other',           'lokasi_idx' => 1, 'admin' => 'admin.cibitung@ithelp.local', 'days_ago' => 12, 'sla' => 'ahead',    'score' => 5, 'rating_komentar' => 'Top markotop!'],

            // overtime (3)
            ['kategori' => 'IT Project',      'lokasi_idx' => 0, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 52, 'sla' => 'overtime', 'score' => 2, 'rating_komentar' => 'Lambat, melewati deadline.'],
            ['kategori' => 'Troubleshooting', 'lokasi_idx' => 4, 'admin' => 'admin.gresik@ithelp.local',   'days_ago' => 47, 'sla' => 'overtime', 'score' => 1, 'rating_komentar' => 'Sangat lambat, perlu improvement.'],
            ['kategori' => 'Other',           'lokasi_idx' => 2, 'admin' => 'admin@ithelp.local',          'days_ago' => 32, 'sla' => 'overtime', 'score' => 3, 'rating_komentar' => 'Akhirnya selesai walau telat.'],
        ];

        foreach ($closeSpecs as $s) {
            $dist[] = [
                'lokasi_idx' => $s['lokasi_idx'],
                'kategori' => $s['kategori'],
                'deskripsi' => $nextDesc($s['kategori']),
                'status' => 'close',
                'admin_email' => $s['admin'],
                'days_ago' => $s['days_ago'],
                'hours_offset' => rand(1, 20),
                'sla_outcome' => $s['sla'],
                'score' => $s['score'],
                'rating_komentar' => $s['rating_komentar'] ?? null,
                'num_komentar' => rand(3, 5),
            ];
        }

        return $dist;
    }

    private function createDemoTicket($demoUser, array $data, $kategoris, array $statuses, $lokasi, $admins): void
    {
        $karyawanKomentar = [
            'Terima kasih. Saya tunggu updatenya.',
            'Sudah saya coba tapi masih error.',
            'Iya, sudah normal sekarang. Terima kasih.',
            'Masih ada masalah, tolong cek lagi.',
            'OK, saya konfirmasi setelah dicoba.',
        ];
        $adminKomentar = [
            'Terima kasih laporannya. Kami sedang mengecek.',
            'Sudah kami proses. Mohon dicoba kembali.',
            'Teknisi sedang menuju lokasi.',
            'Masalah sudah teridentifikasi. Sedang dalam perbaikan.',
            'Sudah selesai diperbaiki. Mohon konfirmasi.',
            'Apakah masih ada masalah setelah perbaikan ini?',
            'Saya eskalasi ke tim vendor, mohon ditunggu.',
        ];

        $admin = $data['admin_email'] ? $admins->get($data['admin_email']) : null;
        $statusKey = $data['status'];
        $status = $statuses[$statusKey];
        $kategori = $kategoris[$data['kategori']];

        $createdAt = now()->subDays($data['days_ago'])->subHours($data['hours_offset']);
        $assignedAt = $admin ? $createdAt->copy()->addMinutes(rand(30, 240)) : null;
        $target = $assignedAt ? $assignedAt->copy()->addHours($kategori->batas_jam_sla) : null;

        $closedAt = null;
        $slaOutcome = null;
        if ($statusKey === 'close' && $target) {
            $slaOutcome = $data['sla_outcome'];
            $slaHours = $kategori->batas_jam_sla;
            $closedAt = match ($slaOutcome) {
                'overtime' => $target->copy()->addHours(rand(2, 24)),
                'ahead' => $assignedAt->copy()->addHours(max(1, intval($slaHours / 3))),
                default => $assignedAt->copy()->addHours(max(1, $slaHours - rand(2, 6))),
            };
        }

        $tiket = Tiket::create([
            'id_pengguna' => $demoUser->id,
            'id_lokasi' => $lokasi[$data['lokasi_idx']]->id,
            'id_kategori' => $kategori->id,
            'deskripsi' => $data['deskripsi'],
            'id_status_tiket' => $status->id,
            'id_penanggung_jawab' => $admin?->id,
            'berulang' => false,
            'target_penyelesaian' => $target,
            'waktu_selesai' => $closedAt,
            'ditutup_pada' => $closedAt,
            'siap_konfirmasi' => false,
            'sla_outcome' => $slaOutcome,
            'created_at' => $createdAt,
            'updated_at' => $closedAt ?? $assignedAt ?? $createdAt,
        ]);

        ActivityLog::create([
            'tiket_id' => $tiket->id,
            'id_pengguna' => $demoUser->id,
            'aksi' => 'ticket_created',
            'status_baru' => 'Open',
            'keterangan' => "New ticket created — category: {$data['kategori']}",
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        if (! $admin) {
            return;
        }

        ActivityLog::create([
            'tiket_id' => $tiket->id,
            'id_pengguna' => $admin->id,
            'aksi' => 'ticket_assigned',
            'status_lama' => 'Open',
            'status_baru' => 'In Progress',
            'keterangan' => 'Admin took the ticket',
            'created_at' => $assignedAt,
            'updated_at' => $assignedAt,
        ]);

        $numKomentar = $data['num_komentar'];
        $commentTimes = [];
        for ($i = 0; $i < $numKomentar; $i++) {
            $hourOffset = ($i + 1) * rand(1, 6);
            $commentAt = $assignedAt->copy()->addHours($hourOffset);

            if ($closedAt && $commentAt > $closedAt) {
                $commentAt = $closedAt->copy()->subMinutes(rand(30, 180));
            }

            $isAdminTurn = ($i % 2 === 0);
            $sender = $isAdminTurn ? $admin : $demoUser;
            $pool = $isAdminTurn ? $adminKomentar : $karyawanKomentar;

            $isiKomentar = $pool[array_rand($pool)];
            KomentarTicket::create([
                'id_tiket' => $tiket->id,
                'id_pengirim' => $sender->id,
                'isi_komentar' => $isiKomentar,
                'created_at' => $commentAt,
                'updated_at' => $commentAt,
            ]);

            ActivityLog::create([
                'tiket_id' => $tiket->id,
                'id_pengguna' => $sender->id,
                'aksi' => 'reply_posted',
                'keterangan' => Str::limit(trim($isiKomentar), 200),
                'created_at' => $commentAt,
                'updated_at' => $commentAt,
            ]);

            $commentTimes[] = $commentAt;
        }

        if ($statusKey === 'close' && $closedAt) {
            $awaitingAt = $closedAt->copy()->subMinutes(rand(30, 120));
            ActivityLog::create([
                'tiket_id' => $tiket->id,
                'id_pengguna' => $admin->id,
                'aksi' => 'status_changed',
                'status_lama' => 'In Progress',
                'status_baru' => 'In Progress (Awaiting Confirmation)',
                'keterangan' => 'Admin marked ticket as resolved — awaiting employee confirmation',
                'created_at' => $awaitingAt,
                'updated_at' => $awaitingAt,
            ]);

            ActivityLog::create([
                'tiket_id' => $tiket->id,
                'id_pengguna' => $demoUser->id,
                'aksi' => 'feedback_solved',
                'keterangan' => 'User confirmed the issue is resolved',
                'created_at' => $closedAt,
                'updated_at' => $closedAt,
            ]);

            ActivityLog::create([
                'tiket_id' => $tiket->id,
                'id_pengguna' => null,
                'aksi' => 'status_changed',
                'status_lama' => 'In Progress',
                'status_baru' => 'Close',
                'keterangan' => 'Ticket closed after employee confirmation',
                'created_at' => $closedAt,
                'updated_at' => $closedAt,
            ]);

            if ($data['score'] !== null) {
                $ratedAt = $closedAt->copy()->addMinutes(rand(10, 180));
                Penilaian::create([
                    'id_tiket' => $tiket->id,
                    'nilai' => $data['score'],
                    'komentar' => $data['rating_komentar'] ?? null,
                    'created_at' => $ratedAt,
                    'updated_at' => $ratedAt,
                ]);

                ActivityLog::create([
                    'tiket_id' => $tiket->id,
                    'id_pengguna' => $demoUser->id,
                    'aksi' => 'rating_submitted',
                    'keterangan' => "Rating: {$data['score']}/5",
                    'created_at' => $ratedAt,
                    'updated_at' => $ratedAt,
                ]);
            }
        }
    }

    /**
     * Group repetitive tickets (same user + kategori + lokasi). No parent_ticket — pure grouping.
     * Just walks all closed tickets and creates groups when multiple matches exist.
     */
    private function seedGrupTiket(): void
    {
        $closeStatusId = StatusTiketModel::findByName('Close')?->id;
        if (! $closeStatusId) {
            return;
        }

        // Group key = (user, kategori, lokasi)
        $groups = Tiket::query()
            ->where('id_status_tiket', $closeStatusId)
            ->select('id_pengguna', 'id_kategori', 'id_lokasi')
            ->groupBy('id_pengguna', 'id_kategori', 'id_lokasi')
            ->havingRaw('COUNT(*) >= 2')
            ->get();

        foreach ($groups as $key) {
            $tikets = Tiket::query()
                ->where('id_pengguna', $key->id_pengguna)
                ->where('id_kategori', $key->id_kategori)
                ->where('id_lokasi', $key->id_lokasi)
                ->orderBy('created_at')
                ->get();

            if ($tikets->count() < 2) {
                continue;
            }

            $latest = $tikets->last();

            $grup = GrupTiket::create([
                'user_id' => $key->id_pengguna,
                'last_ticket' => $latest->id,
                'id_kategori' => $key->id_kategori,
                'id_lokasi' => $key->id_lokasi,
                'id_penanggung_jawab' => $latest->id_penanggung_jawab,
                'jumlah' => $tikets->count(),
            ]);

            foreach ($tikets as $t) {
                $t->update([
                    'grup_tiket_id' => $grup->id,
                    'berulang' => true,
                ]);
            }
        }
    }
}
