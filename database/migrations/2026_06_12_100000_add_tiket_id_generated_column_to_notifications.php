<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generated STORED column: tiket_id is extracted from the JSON payload
        // ONCE at write time, so notification reads (BellBadge on every page,
        // Alerts, AllTickets/MyTickets unread dots) no longer JSON-parse every
        // row — they hit an indexed BIGINT column instead.
        //
        // Non-integer values (key omitted, JSON null, strings) become SQL NULL,
        // preserving the convention that `tiket_id IS NULL` = config/non-ticket
        // notification (see ReferenceArchivedNotification).
        DB::statement("
            ALTER TABLE notifications
            ADD COLUMN tiket_id BIGINT UNSIGNED
            GENERATED ALWAYS AS (
                CASE WHEN JSON_TYPE(JSON_EXTRACT(data, '$.tiket_id')) IN ('INTEGER', 'UNSIGNED INTEGER')
                     THEN CAST(JSON_EXTRACT(data, '$.tiket_id') AS UNSIGNED)
                     ELSE NULL END
            ) STORED
        ");

        Schema::table('notifications', function (Blueprint $table) {
            $table->index('tiket_id', 'notifications_tiket_id_index');
            // Unread-count lookups filter on (notifiable_id, notifiable_type, read_at).
            $table->index(['notifiable_id', 'notifiable_type', 'read_at'], 'notifications_notifiable_read_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_read_index');
            $table->dropIndex('notifications_tiket_id_index');
        });

        DB::statement('ALTER TABLE notifications DROP COLUMN tiket_id');
    }
};
