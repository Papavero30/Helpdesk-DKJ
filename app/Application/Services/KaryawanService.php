<?php

namespace App\Application\Services;

use App\Models\Divisi;
use App\Models\Karyawan;
use App\Models\Lokasi;

class KaryawanService
{
    public function daftarKaryawan()
    {
        return Karyawan::with(['divisi', 'lokasi'])->orderBy('nama')->get();
    }

    public function simpanKaryawan(array $data): Karyawan
    {
        return Karyawan::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'nama' => $data['nama'],
                'no_telepon' => $data['no_telepon'],
                'id_divisi' => $data['id_divisi'] ?? $data['divisi_id'] ?? null,
                'id_lokasi' => $data['id_lokasi'] ?? $data['lokasi_id'] ?? null,
                'email' => $data['email'] ?? null,
            ]
        );
    }

    public function hapusKaryawan(int $id): void
    {
        Karyawan::findOrFail($id)->delete();
    }

    public function daftarDivisi()
    {
        return Divisi::orderBy('nama_divisi')->get();
    }

    public function simpanDivisi(array $data): Divisi
    {
        return Divisi::updateOrCreate(
            ['id' => $data['id'] ?? null],
            ['nama_divisi' => $data['nama_divisi']]
        );
    }

    public function hapusDivisi(int $id): void
    {
        Divisi::findOrFail($id)->delete();
    }

    public function daftarLokasi()
    {
        return Lokasi::orderBy('nama_lokasi')->get();
    }

    public function simpanLokasi(array $data): Lokasi
    {
        return Lokasi::updateOrCreate(
            ['id' => $data['id'] ?? null],
            ['nama_lokasi' => $data['nama_lokasi']]
        );
    }

    public function hapusLokasi(int $id): void
    {
        Lokasi::findOrFail($id)->delete();
    }
}
