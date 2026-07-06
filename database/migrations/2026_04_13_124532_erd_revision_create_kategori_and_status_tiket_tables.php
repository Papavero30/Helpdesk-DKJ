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
        Schema::create('kategori', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kategori')->unique();
            $table->unsignedInteger('sla_hours')->default(48);
            $table->unsignedInteger('urgensi')->default(5);
            $table->string('chart_color')->default('#6b7280');
            $table->timestamps();
        });

        Schema::create('status_tiket', function (Blueprint $table) {
            $table->id();
            $table->string('nama_status_tiket')->unique();
            $table->unsignedInteger('urutan')->default(0);
            $table->string('warna')->default('gray');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_tiket');
        Schema::dropIfExists('kategori');
    }
};
