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
        Schema::create('ml_model_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ml_model_id');
            $table->integer('version_no');
            $table->enum('status', ['CHALLENGER', 'CHAMPION', 'ARCHIVED', 'REJECTED'])->default('CHALLENGER');
            $table->unsignedBigInteger('champion_key')->nullable()->unique()->comment('GENERATED (stored): if((status = _latin1\'CHAMPION\'),ml_model_id,NULL)');
            $table->json('hyperparameters')->nullable();
            $table->dateTime('training_window_start')->nullable();
            $table->dateTime('training_window_end')->nullable();
            $table->integer('training_row_count')->nullable();
            $table->string('artifact_path', 255)->comment('Local file volume only (D8)');
            $table->dateTime('trained_at');
            $table->dateTime('deployed_at')->nullable();
            $table->dateTime('retired_at')->nullable();
            $table->unique(['id', 'ml_model_id'], 'uq_mlv_id_model');
            $table->unique(['ml_model_id', 'version_no'], 'uq_mlv_model_version');

            $table->foreign('ml_model_id', 'fk_mlv_model')->references('id')->on('ml_models');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_model_versions');
    }
};
