<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->boolean('siap_konfirmasi')->default(false)->after('id_penanggung_jawab');
            $table->timestamp('ditutup_pada')->nullable()->after('target_penyelesaian');
            $table->enum('sla_outcome', ['on_time', 'overtime', 'ahead'])->nullable()->after('ditutup_pada');
        });
    }

    public function down(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->dropColumn(['siap_konfirmasi', 'ditutup_pada', 'sla_outcome']);
        });
    }
};
