<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CT-12 (BAPLIE parse) and CT-17 §7: one row per BAPLIE_PARSE job.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('baplie_parse_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('baplie_file_id');
            $table->enum('source', ['FILE', 'STAGED_PAYLOAD'])->default('FILE');
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->enum('status', ['QUEUED', 'RUNNING', 'DONE', 'FAILED', 'REPLACED'])->default('QUEUED');
            $table->enum('outcome', ['PARSED', 'QUARANTINED', 'REJECTED'])->nullable();
            $table->enum('error_category', ['INVALID_ENVELOPE', 'PRECONDITION_FAILED', 'DATA_UNAVAILABLE', 'CONFIGURATION_ERROR', 'TIMEOUT', 'INTERNAL_ERROR'])->nullable();
            $table->tinyInteger('retry_count')->default(0);
            $table->integer('container_count')->nullable();
            $table->json('result_detail')->nullable();
            $table->string('worker_version', 40)->nullable();
            $table->dateTime('queued_at')->useCurrent();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->unsignedBigInteger('active_key')->nullable()->storedAs("IF(`status` IN ('QUEUED', 'RUNNING'), `baplie_file_id`, NULL)");
            $table->dateTime('result_handled_at')->nullable()->comment('Set by the Backend when it has handled the C-06 result (Backend only)');
            $table->unique(['active_key'], 'uq_bpr_active');
            $table->index(['baplie_file_id'], 'idx_bpr_file');

            $table->foreign('baplie_file_id', 'fk_bpr_file')->references('id')->on('baplie_files');
            $table->foreign('triggered_by', 'fk_bpr_user')->references('id')->on('users');
        });

        DB::statement("ALTER TABLE `baplie_parse_runs` ADD CONSTRAINT `chk_bpr_finished` CHECK (status IN ('QUEUED', 'RUNNING', 'REPLACED') OR finished_at IS NOT NULL)");
        DB::statement("ALTER TABLE `baplie_parse_runs` ADD CONSTRAINT `chk_bpr_outcome` CHECK ((status = 'DONE' AND outcome IS NOT NULL) OR (status <> 'DONE' AND outcome IS NULL))");
        DB::statement("ALTER TABLE `baplie_parse_runs` ADD CONSTRAINT `chk_bpr_error` CHECK ((status = 'FAILED') = (error_category IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baplie_parse_runs');
    }
};
