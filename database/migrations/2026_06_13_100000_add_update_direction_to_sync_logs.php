<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddUpdateDirectionToSyncLogs extends Migration
{
    public function up()
    {
        // Allow logging old→new edit re-syncs (syncOldToNewUpdates) without the enum
        // rejecting the new value.
        DB::statement("ALTER TABLE sync_logs MODIFY COLUMN direction ENUM('old_to_new', 'new_to_old', 'old_to_new_update') NOT NULL");
    }

    public function down()
    {
        // Collapse any update rows back to old_to_new before shrinking the enum.
        DB::statement("UPDATE sync_logs SET direction = 'old_to_new' WHERE direction = 'old_to_new_update'");
        DB::statement("ALTER TABLE sync_logs MODIFY COLUMN direction ENUM('old_to_new', 'new_to_old') NOT NULL");
    }
}
