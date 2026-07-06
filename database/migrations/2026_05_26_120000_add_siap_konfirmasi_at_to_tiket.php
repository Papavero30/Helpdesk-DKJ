<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->timestamp('siap_konfirmasi_at')
                ->nullable()
                ->after('siap_konfirmasi');
        });

        // Backfill: existing tickets currently in siap_konfirmasi=true state get
        // updated_at as a proxy for when admin pressed the button. Other rows stay NULL.
        DB::statement('
            UPDATE tiket
            SET siap_konfirmasi_at = updated_at
            WHERE siap_konfirmasi = 1 AND siap_konfirmasi_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->dropColumn('siap_konfirmasi_at');
        });
    }
};
