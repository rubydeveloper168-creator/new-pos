<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('customer_source_id')->unsigned()->nullable()->after('created_by');
            $table->integer('responsible_salesperson_id')->unsigned()->nullable()->after('customer_source_id');

            $table->foreign('responsible_salesperson_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['responsible_salesperson_id']);
            $table->dropColumn(['customer_source_id', 'responsible_salesperson_id']);
        });
    }
};
