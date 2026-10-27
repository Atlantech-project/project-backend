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
        Schema::create('retraining_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ml_model_id');
            $table->unsignedBigInteger('champion_version_id')->nullable()->comment('NULL at cold start');
            $table->unsignedBigInteger('challenger_version_id');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->enum('decision', ['PROMOTED', 'KEPT_CHAMPION', 'FAILED']);
            $table->string('decision_reason', 500)->nullable();
            $table->boolean('review_pending')->nullable()->storedAs('`decision` IN (\'KEPT_CHAMPION\', \'FAILED\') AND `reviewed_at` IS NULL')->comment('GENERATED (stored): ((decision in (_latin1\'KEPT_CHAMPION\',_latin1\'FAILED\')) and (reviewed_at is null)) | TRUE until someone reviews a kept-champion or failed run (D12, P20-02)');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->index(['challenger_version_id', 'ml_model_id'], 'fk_rr_challenger');
            $table->index(['champion_version_id', 'ml_model_id'], 'fk_rr_champion');
            $table->index(['ml_model_id'], 'fk_rr_model');
            $table->index(['reviewed_by'], 'fk_rr_user');

            $table->foreign(['challenger_version_id', 'ml_model_id'], 'fk_rr_challenger')->references(['id', 'ml_model_id'])->on('ml_model_versions');
            $table->foreign(['champion_version_id', 'ml_model_id'], 'fk_rr_champion')->references(['id', 'ml_model_id'])->on('ml_model_versions');
            $table->foreign('ml_model_id', 'fk_rr_model')->references('id')->on('ml_models');
            $table->foreign('reviewed_by', 'fk_rr_user')->references('id')->on('users');

            $table->comment('Champion vs Challenger history. Hard safety limits are never changed by retraining (D12).');
        });

        DB::statement('ALTER TABLE `retraining_runs` ADD CONSTRAINT `chk_rr_review` CHECK (((reviewed_by is null) = (reviewed_at is null)))');
        DB::statement('ALTER TABLE `retraining_runs` ADD CONSTRAINT `chk_rr_time` CHECK (((finished_at is null) or (finished_at >= started_at)))');
        DB::statement('ALTER TABLE `retraining_runs` ADD CONSTRAINT `chk_rr_versions` CHECK (((champion_version_id is null) or (champion_version_id <> challenger_version_id)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retraining_runs');
    }
};
