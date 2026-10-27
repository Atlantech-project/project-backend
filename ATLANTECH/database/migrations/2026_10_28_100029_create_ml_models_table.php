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
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->enum('model_key', ['M2_SERVICE_TIME', 'M3_DEMAND_FORECAST', 'M4_DELAY_RISK', 'FORECAST_BIAS_CORRECTION'])->unique('model_key');
            $table->string('algorithm_family', 40);
            $table->string('description', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};
