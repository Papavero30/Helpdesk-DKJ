<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tiket_berulang');

        Schema::create('grup_tiket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('pengguna')
                ->restrictOnDelete();
            $table->foreignId('parent_ticket')
                ->constrained('tiket')
                ->restrictOnDelete();
            $table->foreignId('last_ticket')
                ->nullable()
                ->constrained('tiket')
                ->nullOnDelete();
            $table->foreignId('id_kategori')
                ->constrained('kategori')
                ->restrictOnDelete();
            $table->foreignId('id_lokasi')
                ->constrained('lokasi')
                ->restrictOnDelete();
            $table->foreignId('id_penanggung_jawab')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete();
            $table->unsignedInteger('jumlah')->default(2);
            $table->timestamps();

            $table->unique('parent_ticket');
            $table->index('user_id');
            $table->index('id_kategori');
            $table->index('id_lokasi');
            $table->index('id_penanggung_jawab');
        });

        Schema::table('tiket', function (Blueprint $table) {
            $table->foreignId('grup_tiket_id')
                ->nullable()
                ->after('berulang')
                ->constrained('grup_tiket')
                ->nullOnDelete();
            $table->index('grup_tiket_id');
        });
    }

    public function down(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->dropForeign(['grup_tiket_id']);
            $table->dropColumn('grup_tiket_id');
        });

        Schema::dropIfExists('grup_tiket');

        Schema::create('tiket_berulang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_ticket')
                ->constrained('tiket')
                ->cascadeOnDelete();
            $table->foreignId('last_ticket')
                ->nullable()
                ->constrained('tiket')
                ->nullOnDelete();
            $table->foreignId('id_kategori')
                ->constrained('kategori')
                ->restrictOnDelete();
            $table->foreignId('id_lokasi')
                ->constrained('lokasi')
                ->restrictOnDelete();
            $table->foreignId('id_penanggung_jawab')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete();
            $table->unsignedInteger('jumlah')->default(2);
            $table->timestamps();

            $table->unique('parent_ticket');
        });
    }
};