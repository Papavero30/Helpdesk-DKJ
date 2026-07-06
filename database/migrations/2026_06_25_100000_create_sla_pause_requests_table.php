<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_pause_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiket_id')->constrained('tiket')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('pengguna');
            $table->timestamp('requested_at');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->timestamp('eta');
            $table->string('state')->default('pending'); // pending|active|resumed|rejected|cancelled
            $table->foreignId('approved_by')->nullable()->constrained('pengguna');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->string('resume_kind')->nullable(); // manual_admin|auto_eta|forced_manager
            $table->unsignedInteger('paused_seconds')->nullable();
            $table->text('decided_note')->nullable();
            $table->timestamps();

            $table->index(['tiket_id', 'state']);
            $table->index('state');
            $table->index('eta');
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_pause_requests');
    }
};
