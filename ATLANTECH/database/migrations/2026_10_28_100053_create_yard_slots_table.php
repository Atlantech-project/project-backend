<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('yard_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('yard_block_id');
            $table->integer('bay');
            $table->integer('row_no');
            $table->integer('tier');
            $table->enum('status', ['ACTIVE', 'BLOCKED'])->default('ACTIVE')->comment('Administrative only; occupied = container_id IS NOT NULL');
            $table->unsignedBigInteger('container_id')->nullable()->unique('container_id');
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()->comment('Version token for the 409 check (microseconds)');
            $table->unique(['id', 'yard_block_id'], 'uq_ys_id_block');
            $table->unique(['yard_block_id', 'bay', 'row_no', 'tier'], 'uq_ys_position');

            $table->foreign('yard_block_id', 'fk_ys_block')->references('id')->on('yard_blocks');
            $table->foreign('container_id', 'fk_ys_container')->references('id')->on('containers');
        });

        DB::statement('ALTER TABLE `yard_slots` ADD CONSTRAINT `chk_ys_blocked_empty` CHECK (((status = \'ACTIVE\') or (container_id is null)))');
        DB::statement('ALTER TABLE `yard_slots` ADD CONSTRAINT `chk_ys_position` CHECK (((bay > 0) and (row_no > 0) and (tier > 0)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yard_slots');
    }
};
