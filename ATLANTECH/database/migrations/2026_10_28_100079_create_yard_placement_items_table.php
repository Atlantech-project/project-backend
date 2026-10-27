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
        Schema::create('yard_placement_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('terminal_recommendation_id');
            $table->enum('rec_type', ['TIMING', 'YARD_PLACEMENT', 'STORAGE_ALLOCATION'])->default('YARD_PLACEMENT');
            $table->unsignedBigInteger('manifest_entry_id');
            $table->unsignedBigInteger('yard_block_id');
            $table->unsignedBigInteger('yard_slot_id')->nullable()->comment('Optional; coordination level is block (FR-2.2.3)');
            $table->index(['yard_block_id'], 'fk_ypi_block');
            $table->index(['manifest_entry_id'], 'fk_ypi_manifest');
            $table->index(['yard_slot_id', 'yard_block_id'], 'fk_ypi_slot');
            $table->index(['terminal_recommendation_id', 'rec_type'], 'fk_ypi_tr');
            $table->unique(['terminal_recommendation_id', 'manifest_entry_id'], 'uq_ypi_rec_container');

            $table->foreign('yard_block_id', 'fk_ypi_block')->references('id')->on('yard_blocks');
            $table->foreign('manifest_entry_id', 'fk_ypi_manifest')->references('id')->on('container_manifest_entries');
            $table->foreign(['yard_slot_id', 'yard_block_id'], 'fk_ypi_slot')->references(['id', 'yard_block_id'])->on('yard_slots');
            $table->foreign(['terminal_recommendation_id', 'rec_type'], 'fk_ypi_tr')->references(['id', 'rec_type'])->on('terminal_recommendations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yard_placement_items');
    }
};
