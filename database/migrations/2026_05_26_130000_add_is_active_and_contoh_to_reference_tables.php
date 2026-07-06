<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kategori', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('warna_grafik');
            $table->text('contoh')->nullable()->after('is_active');
        });

        Schema::table('lokasi', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('nama_lokasi');
        });

        Schema::table('divisi', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('nama_divisi');
        });

        $contohMap = [
            'Troubleshooting' => 'Contoh: laptop tidak nyala, printer paper jam, WiFi disconnect',
            'Security' => 'Contoh: pop-up mencurigakan, akun terkunci, email phishing',
            'CCTV' => 'Contoh: CCTV mati, rekaman blur, DVR offline',
            'IT Project' => 'Contoh: setup workstation baru, instalasi software, migrasi data',
            'Other' => 'Contoh: reset password, request akses VPN, mapping drive',
        ];
        foreach ($contohMap as $nama => $contoh) {
            DB::table('kategori')->where('nama_kategori', $nama)->update(['contoh' => $contoh]);
        }
    }

    public function down(): void
    {
        Schema::table('kategori', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'contoh']);
        });
        Schema::table('lokasi', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        Schema::table('divisi', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
