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
        Schema::create('tank_details', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_facility_id')->primary();
            $table->enum('detail_kind', ['NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK'])->default('TANK')->comment('Same ENUM as parent (FK compares ENUM index)');
            $table->decimal('min_temperature', 5, 2)->nullable()->comment('°C');
            $table->decimal('max_temperature', 5, 2)->nullable()->comment('°C');
            $table->decimal('max_pressure_bar', 6, 2)->nullable()->comment('Pressure limit (bar); NULL = atmospheric tank');
            $table->index(['storage_facility_id', 'detail_kind'], 'fk_td_facility');

            $table->foreign(['storage_facility_id', 'detail_kind'], 'fk_td_facility')->references(['id', 'detail_kind'])->on('storage_facilities');
        });

        DB::statement('ALTER TABLE `tank_details` ADD CONSTRAINT `chk_td_kind` CHECK ((detail_kind = \'TANK\'))');
        DB::statement('ALTER TABLE `tank_details` ADD CONSTRAINT `chk_td_pressure` CHECK (((max_pressure_bar is null) or (max_pressure_bar > 0)))');
        DB::statement('ALTER TABLE `tank_details` ADD CONSTRAINT `chk_td_temp` CHECK (((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tank_details');
    }
};
