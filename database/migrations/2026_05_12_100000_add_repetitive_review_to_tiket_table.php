<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->enum('repetitive_review_state', ['none', 'admin_requested_off', 'user_refused'])
                  ->default('none')->after('berulang');
            $table->index('repetitive_review_state', 'tiket_repetitive_review_state_idx');
            $table->string('repetitive_review_admin_note', 200)->nullable()->after('repetitive_review_state');
            $table->string('repetitive_review_user_note', 200)->nullable()->after('repetitive_review_admin_note');
            $table->timestamp('repetitive_review_admin_at')->nullable()->after('repetitive_review_user_note');
            $table->timestamp('repetitive_review_user_at')->nullable()->after('repetitive_review_admin_at');
        });
    }

    public function down(): void
    {
        Schema::table('tiket', function (Blueprint $table) {
            $table->dropIndex('tiket_repetitive_review_state_idx');
            $table->dropColumn([
                'repetitive_review_state',
                'repetitive_review_admin_note',
                'repetitive_review_user_note',
                'repetitive_review_admin_at',
                'repetitive_review_user_at',
            ]);
        });
    }
};
