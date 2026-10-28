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
        Schema::create('solver_run_model_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('solver_run_id');
            $table->unsignedBigInteger('model_version_id');
            $table->primary(['solver_run_id', 'model_version_id']);
            $table->index(['model_version_id'], 'fk_srmv_model');

            $table->foreign('model_version_id', 'fk_srmv_model')->references('id')->on('ml_model_versions');
            $table->foreign('solver_run_id', 'fk_srmv_run')->references('id')->on('solver_runs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solver_run_model_versions');
    }
};
