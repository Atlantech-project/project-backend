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
        Schema::create('blocked_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proposal_id');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->unsignedBigInteger('limiting_constraint_type_id');
            $table->index(['limiting_constraint_type_id'], 'fk_bp_constraint');
            $table->index(['proposal_id'], 'fk_bp_proposal');

            $table->foreign('limiting_constraint_type_id', 'fk_bp_constraint')->references('id')->on('constraint_types');
            $table->foreign('proposal_id', 'fk_bp_proposal')->references('id')->on('entry_window_proposals');
        });

        DB::statement('ALTER TABLE `blocked_periods` ADD CONSTRAINT `chk_bp_period` CHECK ((period_end > period_start))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_periods');
    }
};
