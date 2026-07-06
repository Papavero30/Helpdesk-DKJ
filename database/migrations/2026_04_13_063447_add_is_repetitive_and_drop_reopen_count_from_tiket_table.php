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
            $table->boolean('is_repetitive')->default(false)->after('batas_waktu_feedback');
            $table->dropColumn('reopen_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->dropColumn('is_repetitive');
            $table->unsignedInteger('reopen_count')->default(0)->after('batas_waktu_feedback');
        });
    }
};
