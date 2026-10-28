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
        Schema::create('tide_prediction_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tide_prediction_run_id');
            $table->dateTime('valid_at');
            $table->decimal('height', 5, 3)->comment('m above datum_reference');
            $table->unique(['tide_prediction_run_id', 'valid_at'], 'uq_tpp_run_time');

            $table->foreign('tide_prediction_run_id', 'fk_tpp_run')->references('id')->on('tide_prediction_runs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tide_prediction_points');
    }
};
