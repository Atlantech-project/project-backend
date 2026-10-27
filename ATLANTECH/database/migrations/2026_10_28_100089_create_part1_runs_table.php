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
        Schema::create('part1_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('berthing_request_id');
            $table->enum('status', ['QUEUED', 'RUNNING', 'DONE', 'FAILED', 'REPLACED'])->default('QUEUED');
            $table->enum('outcome', ['WINDOWS_FOUND', 'NO_FEASIBLE_WINDOW', 'ENV_DATA_UNAVAILABLE'])->nullable();
            $table->tinyInteger('retry_count')->default(0)->comment('0..3');
            $table->unsignedBigInteger('entry_window_proposal_id')->nullable()->comment('Set only when windows are found');
            $table->dateTime('queued_at')->useCurrent();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->foreign('berthing_request_id')->references('id')->on('berthing_requests');
            $table->foreign('entry_window_proposal_id')->references('id')->on('entry_window_proposals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('part1_runs');
    }
};
