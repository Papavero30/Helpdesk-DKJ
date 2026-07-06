<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->timestamp('sla_paused_at')->nullable()->after('repetitive_review_user_at');
            $table->unsignedInteger('sla_paused_total_seconds')->default(0)->after('sla_paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->dropColumn(['sla_paused_at', 'sla_paused_total_seconds']);
        });
    }
};
