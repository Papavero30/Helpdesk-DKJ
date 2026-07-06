<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Lampiran extends Model
{
    protected $table = 'tiket_lampiran';

    protected $fillable = ['tiket_id', 'komentar_id', 'path', 'mime', 'ukuran', 'nama_asli'];

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class, 'tiket_id');
    }

    public function komentar(): BelongsTo
    {
        return $this->belongsTo(KomentarTicket::class, 'komentar_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function url(): string
    {
        return Storage::url($this->path);
    }
}
