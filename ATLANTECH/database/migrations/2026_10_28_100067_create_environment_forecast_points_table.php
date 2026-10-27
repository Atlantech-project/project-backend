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
        Schema::create('environment_forecast_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forecast_run_id');
            $table->dateTime('valid_at');
            $table->decimal('wind_speed', 5, 2)->nullable()->comment('Unit: forecast_run.wind_speed_unit');
            $table->decimal('wind_gust', 5, 2)->nullable()->comment('Unit: forecast_run.wind_speed_unit');
            $table->decimal('wind_direction_deg', 4, 1)->nullable()->comment('Direction the wind comes from, degrees true');
            $table->decimal('visibility', 8, 2)->nullable()->comment('Unit: forecast_run.visibility_unit');
            $table->decimal('wave_height', 4, 2)->nullable()->comment('m (significant)');
            $table->decimal('wave_period', 4, 1)->nullable()->comment('s');
            $table->decimal('wave_direction_deg', 4, 1)->nullable()->comment('Direction the waves come from, degrees true');
            $table->decimal('current_speed', 4, 2)->nullable()->comment('Unit: forecast_run.current_speed_unit');
            $table->decimal('sea_level', 5, 3)->nullable()->comment('m above the active tide datum');
            $table->unique(['forecast_run_id', 'valid_at'], 'uq_efp_run_time');

            $table->foreign('forecast_run_id', 'fk_efp_run')->references('id')->on('forecast_runs');

            $table->comment('Raw provider values; unit conversion and derived values (e.g. surge) are AI-side (P12-02).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('environment_forecast_points');
    }
};
