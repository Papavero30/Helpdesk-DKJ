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
            $table->unsignedInteger('reopen_count')->default(0)->after('batas_waktu_feedback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->dropColumn('reopen_count');
        });
    }
};
