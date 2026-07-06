<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tiket_berulang');

        Schema::create('tiket_berulang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_ticket')
                ->constrained('tiket')->cascadeOnDelete();
            $table->foreignId('last_ticket')
                ->nullable()->constrained('tiket')->nullOnDelete();
            $table->foreignId('id_kategori')
                ->constrained('kategori')->restrictOnDelete();
            $table->foreignId('id_lokasi')
                ->constrained('lokasi')->restrictOnDelete();
            $table->foreignId('id_penanggung_jawab')
                ->nullable()->constrained('pengguna')->nullOnDelete();
            $table->unsignedInteger('jumlah')->default(2);
            $table->timestamps();

            $table->unique('parent_ticket');
            $table->index('id_kategori');
            $table->index('id_lokasi');
            $table->index('id_penanggung_jawab');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiket_berulang');

        Schema::create('tiket_berulang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tiket_asal')->constrained('tiket')->cascadeOnDelete();
            $table->foreignId('id_penanggung_jawab')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->unsignedInteger('jumlah_pengulangan')->default(1);
            $table->timestamps();
            $table->unique('id_tiket_asal');
        });
    }
};
