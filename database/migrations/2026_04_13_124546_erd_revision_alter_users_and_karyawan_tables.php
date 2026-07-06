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
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_account')->default('active')->after('avatar');
            $table->timestamp('last_sign')->nullable()->after('status_account');
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('jabatan')->nullable()->after('plant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status_account', 'last_sign']);
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn('jabatan');
        });
    }
};
