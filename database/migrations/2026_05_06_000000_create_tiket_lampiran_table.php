<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiket_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiket_id')->constrained('tiket')->cascadeOnDelete();
            $table->foreignId('komentar_id')->nullable()->constrained('komentar_tiket')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('ukuran');
            $table->string('nama_asli');
            $table->timestamps();
            $table->index('tiket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiket_lampiran');
    }
};
