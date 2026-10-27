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
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solver_run_id');
            $table->unsignedBigInteger('berthing_request_id');
            $table->unsignedBigInteger('terminal_id')->comment('Copy of the requested terminal, enforced by composite FK');
            $table->unsignedBigInteger('entry_window_proposal_id')->comment('Confirmed Part 1 input of the same request');
            $table->enum('status', ['PENDING_REVIEW', 'CONFIRMED', 'REJECTED', 'INVALIDATED', 'REPLACED', 'MODIFIED_BY_STAFF'])->default('PENDING_REVIEW');
            $table->enum('source', ['AI', 'FIFO'])->default('AI')->comment('FIFO = fallback after a solver technical failure');
            $table->enum('purpose', ['INITIAL', 'DELAY_REPLAN'])->default('INITIAL');
            $table->unsignedBigInteger('active_key')->nullable()->unique()->comment('GENERATED (stored): IF(status IN (PENDING_REVIEW, MODIFIED_BY_STAFF, CONFIRMED) AND purpose = INITIAL, berthing_request_id, NULL)');
            $table->dateTime('generated_at');
            $table->index(['entry_window_proposal_id', 'berthing_request_id'], 'fk_rec_proposal');
            $table->index(['berthing_request_id', 'terminal_id'], 'fk_rec_request');
            $table->index(['solver_run_id'], 'fk_rec_run');
            $table->unique(['id', 'berthing_request_id'], 'uq_rec_id_request');
            $table->unique(['id', 'terminal_id'], 'uq_rec_id_terminal');

            $table->foreign(['entry_window_proposal_id', 'berthing_request_id'], 'fk_rec_proposal')->references(['id', 'berthing_request_id'])->on('entry_window_proposals');
            $table->foreign(['berthing_request_id', 'terminal_id'], 'fk_rec_request')->references(['id', 'requested_terminal_id'])->on('berthing_requests');
            $table->foreign('solver_run_id', 'fk_rec_run')->references('id')->on('solver_runs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
