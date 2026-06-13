<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table tracks the mapping between old database IDs and new database IDs
     * during the migration process. Essential for maintaining referential integrity
     * when migrating related tables.
     */
    public function up(): void
    {
        Schema::create('migration_mappings', function (Blueprint $table) {
            $table->string('old_table', 50);
            $table->unsignedBigInteger('old_id');
            $table->string('new_table', 50);
            $table->unsignedBigInteger('new_id');
            $table->timestamp('migrated_at')->useCurrent();
            $table->text('notes')->nullable();
            
            // Primary key on old table + old id combination
            $table->primary(['old_table', 'old_id']);
            
            // Index for looking up new IDs
            $table->index(['new_table', 'new_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migration_mappings');
    }
};
