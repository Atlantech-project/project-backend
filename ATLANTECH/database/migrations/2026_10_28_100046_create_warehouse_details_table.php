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
        Schema::create('warehouse_details', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_facility_id')->primary();
            $table->enum('detail_kind', ['NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK'])->default('WAREHOUSE')->comment('Same ENUM as parent (FK compares ENUM index)');
            $table->decimal('min_temperature', 5, 2)->nullable()->comment('°C');
            $table->decimal('max_temperature', 5, 2)->nullable()->comment('°C');
            $table->boolean('temperature_monitoring')->default(false);
            $table->index(['storage_facility_id', 'detail_kind'], 'fk_wd_facility');

            $table->foreign(['storage_facility_id', 'detail_kind'], 'fk_wd_facility')->references(['id', 'detail_kind'])->on('storage_facilities');
        });

        DB::statement('ALTER TABLE `warehouse_details` ADD CONSTRAINT `chk_wd_kind` CHECK ((detail_kind = \'WAREHOUSE\'))');
        DB::statement('ALTER TABLE `warehouse_details` ADD CONSTRAINT `chk_wd_temp` CHECK (((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_details');
    }
};
