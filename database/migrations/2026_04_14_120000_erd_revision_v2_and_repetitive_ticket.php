<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->prepareKaryawanColumns();
        $this->migrateUserAndKaryawanData();
        $this->prepareUsersKaryawanColumn();
        $this->prepareTiketUserColumn();
        $this->simplifyStatusTiketTable();
        $this->dropLegacyColumnsAndTables();
        $this->renameColumnsToIndonesian();
        $this->renameTablesAndCreateNew();
        $this->addFinalForeignKeysAndIndexes();
    }

    public function down(): void
    {
        // Reverse table renames first
        if (Schema::hasTable('tiket_berulang')) {
            Schema::drop('tiket_berulang');
        }

        if (Schema::hasTable('komentar_tiket') && ! Schema::hasTable('komentar_ticket')) {
            Schema::rename('komentar_tiket', 'komentar_ticket');
        }

        if (Schema::hasTable('pengguna') && ! Schema::hasTable('users')) {
            Schema::rename('pengguna', 'users');
        }

        // Reverse column renames on users (now back to users)
        $usersTable = Schema::hasTable('users') ? 'users' : 'pengguna';
        Schema::table($usersTable, function (Blueprint $table) use ($usersTable) {
            if (Schema::hasColumn($usersTable, 'id_karyawan')) {
                $table->renameColumn('id_karyawan', 'karyawan_id');
            }
            if (Schema::hasColumn($usersTable, 'peran')) {
                $table->renameColumn('peran', 'role');
            }
            if (Schema::hasColumn($usersTable, 'foto_profil')) {
                $table->renameColumn('foto_profil', 'avatar');
            }
            if (Schema::hasColumn($usersTable, 'status_akun')) {
                $table->renameColumn('status_akun', 'status_account');
            }
            if (Schema::hasColumn($usersTable, 'terakhir_masuk')) {
                $table->renameColumn('terakhir_masuk', 'last_sign');
            }
        });

        if (! Schema::hasTable('plant')) {
            Schema::create('plant', function (Blueprint $table) {
                $table->id();
                $table->string('nama_plant')->unique();
                $table->timestamps();
            });
        }

        Schema::table($usersTable, function (Blueprint $table) use ($usersTable) {
            if (! Schema::hasColumn($usersTable, 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn($usersTable, 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn($usersTable, 'lokasi_id')) {
                $table->unsignedBigInteger('lokasi_id')->nullable();
            }
        });

        Schema::table('karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('karyawan', 'id_lokasi')) {
                $table->renameColumn('id_lokasi', 'lokasi_id');
            }
            if (Schema::hasColumn('karyawan', 'id_divisi')) {
                $table->renameColumn('id_divisi', 'divisi_id');
            }
            if (! Schema::hasColumn('karyawan', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            if (! Schema::hasColumn('karyawan', 'plant_id')) {
                $table->unsignedBigInteger('plant_id')->nullable();
            }
            if (Schema::hasColumn('karyawan', 'email')) {
                $table->dropColumn('email');
            }
        });

        Schema::table('tiket', function (Blueprint $table) {
            if (Schema::hasColumn('tiket', 'id_pengguna')) {
                $table->renameColumn('id_pengguna', 'user_id');
            }
            if (Schema::hasColumn('tiket', 'id_lokasi')) {
                $table->renameColumn('id_lokasi', 'lokasi_id');
            }
            if (Schema::hasColumn('tiket', 'id_kategori')) {
                $table->renameColumn('id_kategori', 'kategori_id');
            }
            if (Schema::hasColumn('tiket', 'id_status_tiket')) {
                $table->renameColumn('id_status_tiket', 'status_tiket_id');
            }
            if (Schema::hasColumn('tiket', 'id_penanggung_jawab')) {
                $table->renameColumn('id_penanggung_jawab', 'pic_user');
            }
            if (Schema::hasColumn('tiket', 'berulang')) {
                $table->renameColumn('berulang', 'is_repetitive');
            }
            if (Schema::hasColumn('tiket', 'waktu_selesai')) {
                $table->renameColumn('waktu_selesai', 'close_time');
            }
            if (! Schema::hasColumn('tiket', 'karyawan_id')) {
                $table->unsignedBigInteger('karyawan_id')->nullable();
            }
            if (! Schema::hasColumn('tiket', 'urutan_antrian')) {
                $table->integer('urutan_antrian')->default(0);
            }
            if (! Schema::hasColumn('tiket', 'batas_waktu_feedback')) {
                $table->dateTime('batas_waktu_feedback')->nullable();
            }
        });

        Schema::table('status_tiket', function (Blueprint $table) {
            if (Schema::hasColumn('status_tiket', 'nama_status')) {
                $table->renameColumn('nama_status', 'nama_status_tiket');
            }
            if (! Schema::hasColumn('status_tiket', 'urutan')) {
                $table->unsignedInteger('urutan')->default(0);
            }
            if (! Schema::hasColumn('status_tiket', 'warna')) {
                $table->string('warna')->default('gray');
            }
        });

        // Reverse komentar renames
        $komentarTable = Schema::hasTable('komentar_ticket') ? 'komentar_ticket' : 'komentar_tiket';
        Schema::table($komentarTable, function (Blueprint $table) use ($komentarTable) {
            if (Schema::hasColumn($komentarTable, 'id_tiket')) {
                $table->renameColumn('id_tiket', 'tiket_id');
            }
            if (Schema::hasColumn($komentarTable, 'id_pengirim')) {
                $table->renameColumn('id_pengirim', 'sender');
            }
        });

        Schema::table('penilaian', function (Blueprint $table) {
            if (Schema::hasColumn('penilaian', 'id_tiket')) {
                $table->renameColumn('id_tiket', 'tiket_id');
            }
            if (Schema::hasColumn('penilaian', 'nilai')) {
                $table->renameColumn('nilai', 'score');
            }
        });

        Schema::table('activity_log', function (Blueprint $table) {
            if (Schema::hasColumn('activity_log', 'id_pengguna')) {
                $table->renameColumn('id_pengguna', 'user_id');
            }
            if (Schema::hasColumn('activity_log', 'aksi')) {
                $table->renameColumn('aksi', 'action');
            }
            if (Schema::hasColumn('activity_log', 'keterangan')) {
                $table->renameColumn('keterangan', 'description');
            }
        });

        Schema::table('kategori', function (Blueprint $table) {
            if (Schema::hasColumn('kategori', 'batas_jam_sla')) {
                $table->renameColumn('batas_jam_sla', 'sla_hours');
            }
            if (Schema::hasColumn('kategori', 'warna_grafik')) {
                $table->renameColumn('warna_grafik', 'chart_color');
            }
        });
    }

    private function prepareKaryawanColumns(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            if (! Schema::hasColumn('karyawan', 'email')) {
                $table->string('email')->nullable()->after('nama');
            }

            if (! Schema::hasColumn('karyawan', 'lokasi_id')) {
                $table->unsignedBigInteger('lokasi_id')->nullable()->after('divisi_id');
            }
        });

        if (! Schema::hasColumn('karyawan', 'plant_id')) {
            return;
        }

        $lokasiFallback = DB::table('lokasi')->value('id');
        $plantNames = Schema::hasTable('plant')
            ? DB::table('plant')->pluck('nama_plant', 'id')
            : collect();
        $lokasiByName = DB::table('lokasi')->pluck('id', 'nama_lokasi');

        DB::table('karyawan')
            ->select('id', 'plant_id', 'lokasi_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $karyawan) use ($lokasiByName, $plantNames, $lokasiFallback): void {
                if ($karyawan->lokasi_id) {
                    return;
                }

                $lokasiId = null;

                if ($karyawan->plant_id) {
                    $lokasiId = $karyawan->plant_id;

                    if ($plantNames->has($karyawan->plant_id)) {
                        $namaPlant = $plantNames->get($karyawan->plant_id);
                        $lokasiId = $lokasiByName->get($namaPlant, $lokasiId);
                    }
                }

                $lokasiId ??= $lokasiFallback;

                if ($lokasiId) {
                    DB::table('karyawan')
                        ->where('id', $karyawan->id)
                        ->update(['lokasi_id' => $lokasiId]);
                }
            });
    }

    private function migrateUserAndKaryawanData(): void
    {
        if (! Schema::hasColumn('karyawan', 'user_id')) {
            $this->normalizeKaryawanEmails();
            return;
        }

        $defaultDivisiId = DB::table('divisi')->value('id');
        if (! $defaultDivisiId) {
            $defaultDivisiId = DB::table('divisi')->insertGetId([
                'nama_divisi' => 'General',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $defaultLokasiId = DB::table('lokasi')->value('id');

        $karyawanByUserId = DB::table('karyawan')
            ->whereNotNull('user_id')
            ->pluck('id', 'user_id');

        $users = DB::table('users')
            ->select('id', 'name', 'email', 'lokasi_id', 'role')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $karyawanId = $karyawanByUserId->get($user->id);

            if ($karyawanId) {
                $this->syncKaryawanFromUser($karyawanId, $user);
                continue;
            }

            $generatedName = $user->name ?: ('User '.$user->id);
            $generatedEmail = $this->normalizeEmail($user->email ?: ('user'.$user->id.'@ithelp.local'));

            $karyawanId = DB::table('karyawan')->insertGetId([
                'user_id' => $user->id,
                'nama' => $generatedName,
                'email' => $generatedEmail,
                'no_telepon' => '-',
                'divisi_id' => $defaultDivisiId,
                'lokasi_id' => $user->lokasi_id ?: $defaultLokasiId,
                'jabatan' => $user->role === 'admin' ? 'Admin' : 'Staff',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $karyawanByUserId->put($user->id, $karyawanId);
        }

        $this->normalizeKaryawanEmails();
    }

    private function prepareUsersKaryawanColumn(): void
    {
        if (! Schema::hasColumn('users', 'karyawan_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('karyawan_id')->nullable()->after('id');
            });
        }

        if (Schema::hasColumn('karyawan', 'user_id')) {
            DB::table('karyawan')
                ->whereNotNull('user_id')
                ->select('id', 'user_id')
                ->orderBy('id')
                ->get()
                ->each(function (object $karyawan): void {
                    DB::table('users')
                        ->where('id', $karyawan->user_id)
                        ->update(['karyawan_id' => $karyawan->id]);
                });
        }
    }

    private function prepareTiketUserColumn(): void
    {
        if (! Schema::hasColumn('tiket', 'user_id')) {
            Schema::table('tiket', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('tiket', 'karyawan_id')) {
            return;
        }

        $userByKaryawan = Schema::hasColumn('karyawan', 'user_id')
            ? DB::table('karyawan')->pluck('user_id', 'id')
            : collect();

        DB::table('tiket')
            ->select('id', 'karyawan_id', 'user_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $tiket) use ($userByKaryawan): void {
                if ($tiket->user_id) {
                    return;
                }

                $userId = $userByKaryawan->get($tiket->karyawan_id);
                if (! $userId) {
                    return;
                }

                DB::table('tiket')
                    ->where('id', $tiket->id)
                    ->update(['user_id' => $userId]);
            });
    }

    private function simplifyStatusTiketTable(): void
    {
        Schema::table('status_tiket', function (Blueprint $table) {
            if (Schema::hasColumn('status_tiket', 'urutan')) {
                $table->dropColumn('urutan');
            }
            if (Schema::hasColumn('status_tiket', 'warna')) {
                $table->dropColumn('warna');
            }
        });
    }

    private function dropLegacyColumnsAndTables(): void
    {
        // Drop old repetitive_ticket table if it exists (will be recreated as tiket_berulang)
        if (Schema::hasTable('repetitive_ticket')) {
            Schema::drop('repetitive_ticket');
        }

        if (Schema::hasColumn('users', 'lokasi_id')) {
            $this->tryDropForeign('users', 'lokasi_id');
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('lokasi_id');
            });
        }

        if (Schema::hasColumn('users', 'email')) {
            $this->tryDropUniqueIndex('users', 'users_email_unique');
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }

        if (Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }

        if (Schema::hasColumn('karyawan', 'user_id')) {
            $this->tryDropForeign('karyawan', 'user_id');
            Schema::table('karyawan', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }

        if (Schema::hasColumn('karyawan', 'plant_id')) {
            $this->tryDropForeign('karyawan', 'plant_id');
            Schema::table('karyawan', function (Blueprint $table) {
                $table->dropColumn('plant_id');
            });
        }

        if (Schema::hasColumn('tiket', 'karyawan_id')) {
            $this->tryDropForeign('tiket', 'karyawan_id');
            Schema::table('tiket', function (Blueprint $table) {
                $table->dropColumn('karyawan_id');
            });
        }

        Schema::table('tiket', function (Blueprint $table) {
            if (Schema::hasColumn('tiket', 'urutan_antrian')) {
                $table->dropColumn('urutan_antrian');
            }
            if (Schema::hasColumn('tiket', 'batas_waktu_feedback')) {
                $table->dropColumn('batas_waktu_feedback');
            }
        });

        if (Schema::hasTable('plant')) {
            Schema::drop('plant');
        }
    }

    private function renameColumnsToIndonesian(): void
    {
        // users table column renames (before table rename)
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'karyawan_id')) {
                $table->renameColumn('karyawan_id', 'id_karyawan');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->renameColumn('role', 'peran');
            }
            if (Schema::hasColumn('users', 'avatar')) {
                $table->renameColumn('avatar', 'foto_profil');
            }
            if (Schema::hasColumn('users', 'status_account')) {
                $table->renameColumn('status_account', 'status_akun');
            }
            if (Schema::hasColumn('users', 'last_sign')) {
                $table->renameColumn('last_sign', 'terakhir_masuk');
            }
        });

        // karyawan column renames
        Schema::table('karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('karyawan', 'lokasi_id')) {
                $table->renameColumn('lokasi_id', 'id_lokasi');
            }
            if (Schema::hasColumn('karyawan', 'divisi_id')) {
                $table->renameColumn('divisi_id', 'id_divisi');
            }
        });

        // status_tiket column renames
        Schema::table('status_tiket', function (Blueprint $table) {
            if (Schema::hasColumn('status_tiket', 'nama_status_tiket')) {
                $table->renameColumn('nama_status_tiket', 'nama_status');
            }
        });

        // tiket column renames
        Schema::table('tiket', function (Blueprint $table) {
            if (Schema::hasColumn('tiket', 'user_id')) {
                $table->renameColumn('user_id', 'id_pengguna');
            }
            if (Schema::hasColumn('tiket', 'lokasi_id')) {
                $table->renameColumn('lokasi_id', 'id_lokasi');
            }
            if (Schema::hasColumn('tiket', 'kategori_id')) {
                $table->renameColumn('kategori_id', 'id_kategori');
            }
            if (Schema::hasColumn('tiket', 'status_tiket_id')) {
                $table->renameColumn('status_tiket_id', 'id_status_tiket');
            }
            if (Schema::hasColumn('tiket', 'pic_user')) {
                $table->renameColumn('pic_user', 'id_penanggung_jawab');
            }
            if (Schema::hasColumn('tiket', 'is_repetitive')) {
                $table->renameColumn('is_repetitive', 'berulang');
            }
            if (Schema::hasColumn('tiket', 'close_time')) {
                $table->renameColumn('close_time', 'waktu_selesai');
            }
        });

        // komentar_ticket column renames (before table rename)
        $komentarTable = Schema::hasTable('komentar_ticket') ? 'komentar_ticket' : 'komentar_tiket';
        Schema::table($komentarTable, function (Blueprint $table) use ($komentarTable) {
            if (Schema::hasColumn($komentarTable, 'tiket_id')) {
                $table->renameColumn('tiket_id', 'id_tiket');
            }
            if (Schema::hasColumn($komentarTable, 'sender')) {
                $table->renameColumn('sender', 'id_pengirim');
            }
        });

        // penilaian column renames
        Schema::table('penilaian', function (Blueprint $table) {
            if (Schema::hasColumn('penilaian', 'tiket_id')) {
                $table->renameColumn('tiket_id', 'id_tiket');
            }
            if (Schema::hasColumn('penilaian', 'score')) {
                $table->renameColumn('score', 'nilai');
            }
        });

        // activity_log column renames (table name stays)
        Schema::table('activity_log', function (Blueprint $table) {
            if (Schema::hasColumn('activity_log', 'user_id')) {
                $table->renameColumn('user_id', 'id_pengguna');
            }
        });
        // Note: activity_log already uses 'aksi' and 'keterangan' from previous migration

        // kategori column renames
        Schema::table('kategori', function (Blueprint $table) {
            if (Schema::hasColumn('kategori', 'sla_hours')) {
                $table->renameColumn('sla_hours', 'batas_jam_sla');
            }
            if (Schema::hasColumn('kategori', 'chart_color')) {
                $table->renameColumn('chart_color', 'warna_grafik');
            }
        });
    }

    private function renameTablesAndCreateNew(): void
    {
        // Rename users → pengguna
        if (Schema::hasTable('users') && ! Schema::hasTable('pengguna')) {
            Schema::rename('users', 'pengguna');
        }

        // Rename komentar_ticket → komentar_tiket
        if (Schema::hasTable('komentar_ticket') && ! Schema::hasTable('komentar_tiket')) {
            Schema::rename('komentar_ticket', 'komentar_tiket');
        }

        // Create tiket_berulang (counter-based, 1 row per parent)
        if (! Schema::hasTable('tiket_berulang')) {
            Schema::create('tiket_berulang', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_tiket_asal')->constrained('tiket')->cascadeOnDelete();
                $table->foreignId('id_penanggung_jawab')->nullable()->constrained('pengguna')->nullOnDelete();
                $table->unsignedInteger('jumlah_pengulangan')->default(1);
                $table->timestamps();

                $table->unique('id_tiket_asal');
            });
        }
    }

    private function addFinalForeignKeysAndIndexes(): void
    {
        if (Schema::hasColumn('pengguna', 'id_karyawan')) {
            $this->tryDropForeign('pengguna', 'id_karyawan');
            // Also try old FK name
            $this->tryDropForeign('pengguna', 'karyawan_id');
            try {
                Schema::table('pengguna', function (Blueprint $table) {
                    $table->dropForeign(['users_karyawan_id_foreign']);
                });
            } catch (\Throwable) {}

            Schema::table('pengguna', function (Blueprint $table) {
                $table->foreign('id_karyawan')->references('id')->on('karyawan')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('karyawan', 'id_lokasi')) {
            $this->tryDropForeign('karyawan', 'id_lokasi');
            $this->tryDropForeign('karyawan', 'lokasi_id');
            Schema::table('karyawan', function (Blueprint $table) {
                $table->foreign('id_lokasi')->references('id')->on('lokasi')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('karyawan', 'email')) {
            $this->tryDropUniqueIndex('karyawan', 'karyawan_email_unique');
            Schema::table('karyawan', function (Blueprint $table) {
                $table->unique('email');
            });
        }

        if (Schema::hasColumn('tiket', 'id_pengguna')) {
            $this->tryDropForeign('tiket', 'id_pengguna');
            $this->tryDropForeign('tiket', 'user_id');
            Schema::table('tiket', function (Blueprint $table) {
                $table->foreign('id_pengguna')->references('id')->on('pengguna')->cascadeOnDelete();
            });
        }
    }

    private function syncKaryawanFromUser(int $karyawanId, object $user): void
    {
        $karyawan = DB::table('karyawan')->where('id', $karyawanId)->first();

        if (! $karyawan) {
            return;
        }

        $update = [];

        if (empty($karyawan->nama) && ! empty($user->name)) {
            $update['nama'] = $user->name;
        }

        if (empty($karyawan->email) && ! empty($user->email)) {
            $update['email'] = $this->normalizeEmail($user->email);
        }

        $lokasiCol = Schema::hasColumn('karyawan', 'lokasi_id') ? 'lokasi_id' : 'id_lokasi';
        if (empty($karyawan->$lokasiCol) && ! empty($user->lokasi_id)) {
            $update[$lokasiCol] = $user->lokasi_id;
        }

        if ($update !== []) {
            DB::table('karyawan')
                ->where('id', $karyawanId)
                ->update($update);
        }
    }

    private function normalizeKaryawanEmails(): void
    {
        $used = [];

        DB::table('karyawan')
            ->select('id', 'email')
            ->orderBy('id')
            ->get()
            ->each(function (object $karyawan) use (&$used): void {
                $base = $this->normalizeEmail($karyawan->email ?: ('karyawan'.$karyawan->id.'@ithelp.local'));
                $email = $base;
                $counter = 1;

                while (in_array($email, $used, true)) {
                    $email = preg_replace('/@/', '+'.$counter.'@', $base, 1) ?: ('karyawan'.$karyawan->id.'+'.$counter.'@ithelp.local');
                    $counter++;
                }

                $used[] = $email;

                if ($email !== $karyawan->email) {
                    DB::table('karyawan')
                        ->where('id', $karyawan->id)
                        ->update(['email' => $email]);
                }
            });
    }

    private function normalizeEmail(?string $email): string
    {
        return strtolower(trim($email ?: ''));
    }

    private function tryDropForeign(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // Ignore when foreign key does not exist.
        }
    }

    private function tryDropUniqueIndex(string $table, string $index): void
    {
        try {
            DB::statement('ALTER TABLE `'.$table.'` DROP INDEX `'.$index.'`');
        } catch (\Throwable) {
            // Ignore when index does not exist.
        }
    }
};
