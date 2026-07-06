<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['tiket_id']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('tiket_id')->nullable()->change();
            $table->foreign('tiket_id')->references('id')->on('tiket')->nullOnDelete();
            $table->string('entity_type')->nullable()->after('tiket_id');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['tiket_id']);
            $table->dropColumn(['entity_type', 'entity_id']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('tiket_id')->nullable(false)->change();
            $table->foreign('tiket_id')->references('id')->on('tiket')->cascadeOnDelete();
        });
    }
};
