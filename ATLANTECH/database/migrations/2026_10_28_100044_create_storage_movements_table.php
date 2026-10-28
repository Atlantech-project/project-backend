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
        Schema::create('storage_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('storage_facility_id');
            $table->unsignedBigInteger('cargo_type_id');
            $table->enum('direction', ['IN', 'OUT']);
            $table->decimal('quantity', 14, 2)->comment('In the facility capacity_unit');
            $table->enum('source', ['VESSEL_DISCHARGE', 'VESSEL_LOADING', 'MANUAL']);
            $table->unsignedBigInteger('plan_component_id')->nullable()->comment('FK added in Module 18 (D-49); NULL for MANUAL');
            $table->dateTime('occurred_at')->comment('UTC');
            $table->unsignedBigInteger('recorded_by')->nullable()->comment('user.id (Module 3); NULL = recorded by the system');
            $table->index(['storage_facility_id', 'cargo_type_id'], 'fk_sm_compatible');
            $table->index(['plan_component_id'], 'fk_sm_plan_component');
            $table->index(['recorded_by'], 'fk_sm_user');
            $table->index(['storage_facility_id', 'occurred_at'], 'idx_sm_facility_time');

            $table->foreign(['storage_facility_id', 'cargo_type_id'], 'fk_sm_compatible')->references(['storage_facility_id', 'cargo_type_id'])->on('storage_facility_cargo_compatibilities');
            $table->foreign('plan_component_id', 'fk_sm_plan_component')->references('id')->on('plan_components');
            $table->foreign('recorded_by', 'fk_sm_user')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `storage_movements` ADD CONSTRAINT `chk_sm_quantity` CHECK ((quantity > 0))');
        DB::statement('ALTER TABLE `storage_movements` ADD CONSTRAINT `chk_sm_source_direction` CHECK ((((source = \'VESSEL_DISCHARGE\') and (direction = \'IN\')) or ((source = \'VESSEL_LOADING\') and (direction = \'OUT\')) or (source = \'MANUAL\')))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_movements');
    }
};
