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
        Schema::create('entry_window_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('berthing_request_id');
            $table->unsignedBigInteger('forecast_run_id');
            $table->unsignedBigInteger('tide_prediction_run_id');
            $table->unsignedBigInteger('bias_correction_version_id')->nullable();
            $table->decimal('peak_congestion_score', 4, 3)->nullable();
            $table->enum('status', ['PROPOSED', 'CONFIRMED', 'REPLACED', 'REJECTED'])->default('PROPOSED');
            $table->unsignedBigInteger('confirmed_key')->nullable()->unique()->comment('GENERATED (stored): if((status = \'CONFIRMED\'),berthing_request_id,NULL)');
            $table->dateTime('generated_at');
            $table->unsignedBigInteger('confirmed_window_id')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable()->comment('Required when REJECTED (all windows rejected)');
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->index(['bias_correction_version_id'], 'fk_ewp_bias');
            $table->index(['forecast_run_id'], 'fk_ewp_forecast');
            $table->index(['berthing_request_id'], 'fk_ewp_request');
            $table->index(['tide_prediction_run_id'], 'fk_ewp_tide');
            $table->index(['confirmed_by'], 'fk_ewp_user');
            $table->index(['confirmed_window_id', 'id'], 'fk_ewp_window');
            $table->unique(['id', 'berthing_request_id'], 'uq_ewp_id_request');

            $table->foreign('bias_correction_version_id', 'fk_ewp_bias')->references('id')->on('ml_model_versions');
            $table->foreign('forecast_run_id', 'fk_ewp_forecast')->references('id')->on('forecast_runs');
            $table->foreign('berthing_request_id', 'fk_ewp_request')->references('id')->on('berthing_requests');
            $table->foreign('tide_prediction_run_id', 'fk_ewp_tide')->references('id')->on('tide_prediction_runs');
            $table->foreign('confirmed_by', 'fk_ewp_user')->references('id')->on('users');
            $table->foreign(['confirmed_window_id', 'id'], 'fk_ewp_window')->references(['id', 'proposal_id'])->on('feasible_windows');
            $table->foreign('rejected_by', 'fk_ewp_rejected_by')->references('id')->on('users');

            $table->comment('Part 1 output (D2). Confirmed window feeds Part 2 (D3). data_versions = the three run FKs.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entry_window_proposals');
    }
};
