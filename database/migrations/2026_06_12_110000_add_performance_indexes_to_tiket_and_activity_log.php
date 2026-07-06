<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            // Date-range filters + sorts (AllTickets date filter, report ranges, trend series).
            $table->index('created_at', 'tiket_created_at_index');
            // Resolved-series bucketing + SLA outcome checks (report trendSeries groups by ditutup_pada).
            $table->index('ditutup_pada', 'tiket_ditutup_pada_index');
            // PIC ownership + status scans (MyQueue, MyTickets-mode tab counts, unread-dot join,
            // pickup queue "unassigned + Open" lookups benefit via leftmost prefix).
            $table->index(['id_penanggung_jawab', 'id_status_tiket'], 'tiket_pic_status_index');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            // Activity Log page always orders by created_at desc with a limit.
            $table->index('created_at', 'activity_log_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->dropIndex('tiket_created_at_index');
            $table->dropIndex('tiket_ditutup_pada_index');
            $table->dropIndex('tiket_pic_status_index');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_created_at_index');
        });
    }
};
