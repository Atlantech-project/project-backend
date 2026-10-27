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
        Schema::create('solver_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('trigger_type', ['NEW_REQUEST', 'REPLAN', 'RESOURCE_CHANGE', 'MANUAL']);
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->dateTime('horizon_start');
            $table->dateTime('horizon_end');
            $table->enum('status', ['QUEUED', 'RUNNING', 'OPTIMAL', 'FEASIBLE', 'INFEASIBLE', 'ERROR'])->default('QUEUED');
            $table->decimal('objective_value', 14, 4)->nullable();
            $table->integer('solve_time_ms')->nullable();
            $table->string('solver_version', 40);
            $table->json('result_detail')->nullable()->comment('INFEASIBLE: binding constraints; ERROR: error message');
            $table->dateTime('queued_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->index(['triggered_by'], 'fk_sr_user');

            $table->foreign('triggered_by', 'fk_sr_user')->references('id')->on('users');

            $table->comment('Port-wide joint solve (FR-1.4.1). INFEASIBLE is final; FIFO fallback only on technical failure (recommendation.source = FIFO).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solver_runs');
    }
};
