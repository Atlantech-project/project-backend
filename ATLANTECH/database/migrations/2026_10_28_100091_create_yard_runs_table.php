<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CT-11 (yard placement) and CT-17 §7: one row per YARD_PLACEMENT job.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('yard_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('terminal_recommendation_id');
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->enum('status', ['QUEUED', 'RUNNING', 'DONE', 'FAILED', 'REPLACED'])->default('QUEUED');
            $table->enum('outcome', ['YARD_REC_GENERATED', 'GENERATION_FAILED'])->nullable();
            $table->enum('error_category', ['INVALID_ENVELOPE', 'PRECONDITION_FAILED', 'DATA_UNAVAILABLE', 'CONFIGURATION_ERROR', 'INFEASIBLE', 'TIMEOUT', 'INTERNAL_ERROR'])->nullable();
            $table->tinyInteger('retry_count')->default(0);
            $table->json('result_detail')->nullable();
            $table->string('worker_version', 40)->nullable();
            $table->dateTime('queued_at')->useCurrent();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->unsignedBigInteger('active_key')->nullable()->storedAs("IF(`status` IN ('QUEUED', 'RUNNING'), `terminal_recommendation_id`, NULL)");
            $table->dateTime('result_handled_at')->nullable()->comment('Set by the Backend when it has handled the C-06 result (Backend only)');
            $table->unique(['active_key'], 'uq_yr_active');
            $table->index(['terminal_recommendation_id'], 'idx_yr_tr');

            $table->foreign('terminal_recommendation_id', 'fk_yr_tr')->references('id')->on('terminal_recommendations');
            $table->foreign('triggered_by', 'fk_yr_user')->references('id')->on('users');
        });

        DB::statement("ALTER TABLE `yard_runs` ADD CONSTRAINT `chk_yr_finished` CHECK (status IN ('QUEUED', 'RUNNING', 'REPLACED') OR finished_at IS NOT NULL)");
        DB::statement("ALTER TABLE `yard_runs` ADD CONSTRAINT `chk_yr_outcome` CHECK ((status = 'DONE' AND outcome = 'YARD_REC_GENERATED') OR (status = 'FAILED' AND outcome = 'GENERATION_FAILED') OR (status IN ('QUEUED', 'RUNNING', 'REPLACED') AND outcome IS NULL))");
        DB::statement("ALTER TABLE `yard_runs` ADD CONSTRAINT `chk_yr_error` CHECK ((status = 'FAILED') = (error_category IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yard_runs');
    }
};
