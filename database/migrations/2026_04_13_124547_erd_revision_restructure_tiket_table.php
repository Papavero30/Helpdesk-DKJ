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
        Schema::table('tiket', function (Blueprint $table) {
            // Add new FK columns
            $table->foreignId('kategori_id')->nullable()->after('lokasi_id')->constrained('kategori')->nullOnDelete();
            $table->foreignId('status_tiket_id')->nullable()->after('kategori_id')->constrained('status_tiket')->nullOnDelete();
            $table->dateTime('target_penyelesaian')->nullable()->after('batas_waktu_feedback');
            $table->timestamp('close_time')->nullable()->after('target_penyelesaian');

            // Rename assigned_to → pic_user
            $table->renameColumn('assigned_to', 'pic_user');
        });

        // Drop old string columns (separate schema call for SQLite/MySQL compat)
        Schema::table('tiket', function (Blueprint $table) {
            $table->dropColumn(['kategori', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->string('kategori')->default('other');
            $table->string('status')->default('open');
            $table->renameColumn('pic_user', 'assigned_to');
        });

        Schema::table('tiket', function (Blueprint $table) {
            $table->dropForeign(['kategori_id']);
            $table->dropForeign(['status_tiket_id']);
            $table->dropColumn(['kategori_id', 'status_tiket_id', 'target_penyelesaian', 'close_time']);
        });
    }
};
