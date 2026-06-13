<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSyncIndexesToTransactions extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Used by the old→new create dedup/self-heal lookup (where old_pos_sale_id = ?).
            $table->index('old_pos_sale_id', 'transactions_old_pos_sale_id_idx');
            // Used by the new→old pending selection that scans every minute
            // (where synced_to_old_pos = 0 and sync_source is null).
            $table->index(['synced_to_old_pos', 'sync_source'], 'transactions_sync_state_idx');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_old_pos_sale_id_idx');
            $table->dropIndex('transactions_sync_state_idx');
        });
    }
}
