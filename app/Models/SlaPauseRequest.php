<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SlaPauseRequest extends Model
{
    protected $table = 'sla_pause_requests';

    protected $fillable = [
        'tiket_id', 'requested_by', 'requested_at', 'reason', 'attachment_path',
        'eta', 'state', 'approved_by', 'approved_at', 'resumed_at', 'resume_kind',
        'paused_seconds', 'decided_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'eta' => 'datetime',
            'approved_at' => 'datetime',
            'resumed_at' => 'datetime',
            'paused_seconds' => 'integer',
        ];
    }

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class, 'tiket_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Display name of the admin who requested the pause (the ticket PIC). */
    public function adminName(): string
    {
        $admin = $this->requester;

        return $admin?->karyawan?->nama ?? $admin?->name ?? 'the admin';
    }

    /** Display name of the ticket requester (the employee who owns the ticket). */
    public function requesterName(): string
    {
        $user = $this->tiket?->user;

        return $user?->karyawan?->nama ?? $user?->name ?? 'the requester';
    }

    /** Ticket reference with its description, e.g. #TKT43 "Printer not working". */
    public function ticketRef(): string
    {
        $number = '#TKT'.str_pad((string) $this->tiket_id, 2, '0', STR_PAD_LEFT);
        $desc = Str::limit(trim((string) ($this->tiket?->deskripsi ?? '')), 60);

        return $desc !== '' ? $number.' "'.$desc.'"' : $number;
    }
}
