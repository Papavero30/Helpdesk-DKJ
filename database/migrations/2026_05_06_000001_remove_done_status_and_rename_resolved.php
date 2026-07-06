<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $doneId = DB::table('status_tiket')->where('nama_status', 'Done')->value('id');
        $inProgressId = DB::table('status_tiket')->where('nama_status', 'In Progress')->value('id');

        if ($doneId && $inProgressId) {
            DB::table('tiket')->where('id_status_tiket', $doneId)->update(['id_status_tiket' => $inProgressId]);
            DB::table('status_tiket')->where('id', $doneId)->delete();
        }

        DB::table('status_tiket')->where('nama_status', 'Resolved')->update(['nama_status' => 'Close']);
    }

    public function down(): void
    {
        DB::table('status_tiket')->where('nama_status', 'Close')->update(['nama_status' => 'Resolved']);

        DB::table('status_tiket')->insert([
            'nama_status' => 'Done',
            'urutan' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
