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
        Schema::create('evaluation_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('model_version_id')->nullable()->comment('NULL for system-level metrics');
            $table->unsignedBigInteger('retraining_run_id')->nullable();
            $table->enum('metric_name', ['MAE', 'OVERRIDE_RATE', 'WAIT_TIME', 'WINDOW_ACCURACY']);
            $table->enum('dataset_type', ['HOLDOUT', 'ROLLING', 'PRODUCTION']);
            $table->decimal('metric_value', 14, 5);
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->dateTime('computed_at');
            $table->index(['retraining_run_id'], 'fk_em_run');
            $table->index(['model_version_id'], 'fk_em_version');

            $table->foreign('retraining_run_id', 'fk_em_run')->references('id')->on('retraining_runs');
            $table->foreign('model_version_id', 'fk_em_version')->references('id')->on('ml_model_versions');
        });

        DB::statement('ALTER TABLE `evaluation_metrics` ADD CONSTRAINT `chk_em_period` CHECK ((period_end > period_start))');
        DB::statement('ALTER TABLE `evaluation_metrics` ADD CONSTRAINT `chk_em_scope` CHECK ((((metric_name <> \'MAE\') or (model_version_id is not null)) and ((metric_name not in (\'OVERRIDE_RATE\',\'WAIT_TIME\')) or (model_version_id is null))))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_metrics');
    }
};
