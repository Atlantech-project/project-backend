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
        Schema::create('demand_forecast_slots', function (Blueprint $table) {
            $table->id();
            $table->dateTime('generated_at');
            $table->dateTime('slot_start');
            $table->dateTime('slot_end');
            $table->integer('expected_arrivals');
            $table->decimal('congestion_score', 4, 3);
            $table->enum('method', ['AGGREGATION', 'LIGHTGBM']);
            $table->unsignedBigInteger('model_version_id')->nullable()->comment('NULL in Phase 1 (aggregation)');
            $table->index(['model_version_id'], 'fk_dfs_model');
            $table->index(['slot_start'], 'idx_dfs_slot');

            $table->foreign('model_version_id', 'fk_dfs_model')->references('id')->on('ml_model_versions');
        });

        DB::statement('ALTER TABLE `demand_forecast_slots` ADD CONSTRAINT `chk_dfs_method` CHECK (((method = \'LIGHTGBM\') = (model_version_id is not null)))');
        DB::statement('ALTER TABLE `demand_forecast_slots` ADD CONSTRAINT `chk_dfs_values` CHECK (((slot_end > slot_start) and (expected_arrivals >= 0) and (congestion_score between 0 and 1)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_forecast_slots');
    }
};
