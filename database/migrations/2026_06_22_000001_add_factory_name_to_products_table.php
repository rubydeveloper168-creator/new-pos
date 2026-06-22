<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('products', 'factory_name')) {
            $afterColumn = Schema::hasColumn('products', 'second_name') ? 'second_name' : 'name';

            Schema::table('products', function (Blueprint $table) use ($afterColumn) {
                $table->string('factory_name')->nullable()->after($afterColumn);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'factory_name')) {
                $table->dropColumn('factory_name');
            }
        });
    }
};
