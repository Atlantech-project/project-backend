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
        Schema::create('tide_prediction_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('constituent_set_id');
            $table->dateTime('computed_at');
            $table->dateTime('horizon_start');
            $table->dateTime('horizon_end');
            $table->index(['constituent_set_id'], 'fk_tpr_set');

            $table->foreign('constituent_set_id', 'fk_tpr_set')->references('id')->on('tide_constituent_sets');

            $table->comment('Daily in-house harmonic computation (D7, D10).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tide_prediction_runs');
    }
};
