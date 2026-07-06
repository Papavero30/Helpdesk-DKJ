<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jabatan', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->foreignId('id_jabatan')->nullable()->after('jabatan')
                ->constrained('jabatan')->nullOnDelete();
        });

        // Backfill existing data: distinct jabatan strings → jabatan rows → link.
        $names = DB::table('karyawan')->whereNotNull('jabatan')->distinct()->pluck('jabatan');
        foreach ($names as $name) {
            $id = DB::table('jabatan')->where('nama_jabatan', $name)->value('id')
                ?? DB::table('jabatan')->insertGetId([
                    'nama_jabatan' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            DB::table('karyawan')->where('jabatan', $name)->update(['id_jabatan' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropForeign(['id_jabatan']);
            $table->dropColumn('id_jabatan');
        });
        Schema::dropIfExists('jabatan');
    }
};
