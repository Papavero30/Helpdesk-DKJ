<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grup_tiket', function (Blueprint $table) {
            $table->dropForeign(['parent_ticket']);
            $table->dropUnique(['parent_ticket']);
            $table->dropColumn('parent_ticket');
        });
    }

    public function down(): void
    {
        Schema::table('grup_tiket', function (Blueprint $table) {
            $table->foreignId('parent_ticket')->nullable()->after('user_id')->constrained('tiket');
        });
    }
};
