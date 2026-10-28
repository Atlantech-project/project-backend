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
        Schema::create('feasible_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proposal_id');
            $table->dateTime('window_start');
            $table->dateTime('window_end');
            $table->decimal('confidence', 4, 3)->nullable();
            $table->index(['proposal_id'], 'fk_fw_proposal');
            $table->unique(['id', 'proposal_id'], 'uq_fw_id_proposal');
        });

        DB::statement('ALTER TABLE `feasible_windows` ADD CONSTRAINT `chk_fw_values` CHECK (((window_end > window_start) and ((confidence is null) or (confidence between 0 and 1))))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feasible_windows');
    }
};
