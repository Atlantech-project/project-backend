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
        Schema::create('forecast_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 80)->comment('Weather provider');
            $table->enum('pull_type', ['SCHEDULED', 'ON_DEMAND', 'MANUAL']);
            $table->enum('provider_rank', ['PRIMARY', 'SECONDARY', 'MANUAL'])->nullable()->comment('Which provider supplied the data');
            $table->boolean('data_stale')->default(false)->comment('1 = latest data older than the allowed age');
            $table->unsignedBigInteger('berthing_request_id')->nullable()->comment('Set only for ON_DEMAND (Customs Approval)');
            $table->unsignedBigInteger('submitted_by')->nullable()->comment('Required when pull_type = MANUAL');
            $table->enum('status', ['SUCCESS', 'FAILED']);
            $table->dateTime('pulled_at');
            $table->dateTime('horizon_start')->nullable();
            $table->dateTime('horizon_end')->nullable();
            $table->enum('wind_speed_unit', ['KNOTS', 'METRES_PER_SECOND', 'KM_PER_HOUR'])->comment('Unit of wind_speed / wind_gust as sent by the provider');
            $table->enum('current_speed_unit', ['KNOTS', 'METRES_PER_SECOND', 'KM_PER_HOUR']);
            $table->enum('visibility_unit', ['NAUTICAL_MILES', 'KILOMETRES', 'METRES']);
            $table->index(['berthing_request_id'], 'fk_fr_request');
            $table->index(['submitted_by'], 'fk_fr_submitted_by');
            $table->index(['pulled_at'], 'idx_fr_pulled');

            $table->foreign('berthing_request_id', 'fk_fr_request')->references('id')->on('berthing_requests');
            $table->foreign('submitted_by', 'fk_fr_submitted_by')->references('id')->on('users');

            $table->comment('Location = port_profile lat/lon (single port).');
        });

        DB::statement('ALTER TABLE `forecast_runs` ADD CONSTRAINT `chk_fr_horizon` CHECK (((horizon_start is null) or (horizon_end is null) or (horizon_end > horizon_start)))');
        DB::statement('ALTER TABLE `forecast_runs` ADD CONSTRAINT `chk_fr_on_demand` CHECK ((`pull_type` = \'SCHEDULED\') = (`berthing_request_id` IS NULL))');
        DB::statement('ALTER TABLE `forecast_runs` ADD CONSTRAINT `chk_fr_manual` CHECK ((`pull_type` = \'MANUAL\') = (`submitted_by` IS NOT NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_runs');
    }
};
