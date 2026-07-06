<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename balasan_tiket → komentar_ticket + rename columns
        Schema::rename('balasan_tiket', 'komentar_ticket');

        Schema::table('komentar_ticket', function (Blueprint $table) {
            $table->renameColumn('user_id', 'sender');
            $table->renameColumn('isi_balasan', 'isi_komentar');
        });

        // Simplify penilaian: rename bintang → score, drop denormalized columns
        Schema::table('penilaian', function (Blueprint $table) {
            $table->renameColumn('bintang', 'score');
        });

        Schema::table('penilaian', function (Blueprint $table) {
            $table->dropForeign(['karyawan_id']);
            $table->dropColumn('karyawan_id');
            if (Schema::hasColumn('penilaian', 'admin_id')) {
                $table->dropForeign(['admin_id']);
                $table->dropColumn('admin_id');
            }
            if (Schema::hasColumn('penilaian', 'kategori')) {
                $table->dropColumn('kategori');
            }
        });
    }

    public function down(): void
    {
        Schema::table('penilaian', function (Blueprint $table) {
            $table->foreignId('karyawan_id')->nullable()->constrained('karyawan')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kategori')->nullable();
            $table->renameColumn('score', 'bintang');
        });

        Schema::table('komentar_ticket', function (Blueprint $table) {
            $table->renameColumn('sender', 'user_id');
            $table->renameColumn('isi_komentar', 'isi_balasan');
        });

        Schema::rename('komentar_ticket', 'balasan_tiket');
    }
};
