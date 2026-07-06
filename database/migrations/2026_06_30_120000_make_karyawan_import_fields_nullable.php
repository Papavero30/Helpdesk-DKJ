<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bulk-imported accounts (from the mailbox export) only carry name, email,
     * and title. Divisi, Plant, and phone are filled in later once company data
     * is finalized, so these columns must accept NULL. (email & id_lokasi are
     * already nullable; only no_telepon and id_divisi are NOT NULL.)
     */
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropForeign('karyawan_divisi_id_foreign');
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('no_telepon')->nullable()->change();
            $table->unsignedBigInteger('id_divisi')->nullable()->change();
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->foreign('id_divisi')->references('id')->on('divisi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropForeign(['id_divisi']);
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('no_telepon')->nullable(false)->change();
            $table->unsignedBigInteger('id_divisi')->nullable(false)->change();
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->foreign('id_divisi')->references('id')->on('divisi')->cascadeOnDelete();
        });
    }
};
