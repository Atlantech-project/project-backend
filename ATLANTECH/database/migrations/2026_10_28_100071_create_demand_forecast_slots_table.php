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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_forecast_slots');
    }
};
