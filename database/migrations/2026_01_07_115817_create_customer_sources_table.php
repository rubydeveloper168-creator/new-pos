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
        Schema::create('customer_sources', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('business_id')
                ->references('id')
                ->on('business')
                ->onDelete('cascade');
        });

        // Insert default customer sources
        DB::table('customer_sources')->insert([
            ['business_id' => 1, 'name' => 'Facebook', 'is_active' => 1, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => 1, 'name' => 'Line OA', 'is_active' => 1, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => 1, 'name' => 'Youtube', 'is_active' => 1, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => 1, 'name' => 'TikTok', 'is_active' => 1, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => 1, 'name' => 'Phone', 'is_active' => 1, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => 1, 'name' => 'Other', 'is_active' => 1, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_sources');
    }
};
