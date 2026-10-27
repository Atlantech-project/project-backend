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
        Schema::create('storage_facilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('terminal_id');
            $table->enum('terminal_type', ['CONTAINER', 'NON_CONTAINER'])->comment('Copy of terminal.terminal_type, enforced by composite FK');
            $table->unsignedBigInteger('storage_type_id');
            $table->enum('detail_kind', ['NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK'])->comment('Copy of storage_type.detail_kind, enforced by composite FK');
            $table->string('name', 100);
            $table->string('code', 30);
            $table->decimal('area', 12, 2)->nullable()->comment('m2');
            $table->decimal('max_floor_load', 6, 2)->nullable()->comment('Ground/floor weight limit, tonnes per m2 (open yard, warehouse); NULL = not applicable');
            $table->decimal('capacity', 14, 2)->nullable()->comment('NULL for CONTAINER_YARD (derived from yard_slot)');
            $table->enum('capacity_unit', ['TONNES', 'CUBIC_METRES', 'SQUARE_METRES', 'TEU', 'UNITS'])->nullable()->comment('NULL for CONTAINER_YARD');
            $table->decimal('current_occupancy', 14, 2)->default(0);
            $table->decimal('reserved_capacity', 14, 2)->default(0);
            $table->decimal('blocked_capacity', 14, 2)->default(0);
            $table->decimal('available_capacity', 14, 2)->nullable()->storedAs('`capacity` - `current_occupancy` - `reserved_capacity` - `blocked_capacity`')->comment('GENERATED (stored): (((capacity - current_occupancy) - reserved_capacity) - blocked_capacity)');
            $table->enum('status', ['ACTIVE', 'MAINTENANCE', 'INACTIVE'])->default('ACTIVE')->comment('Administrative only; fullness comes from the capacity numbers');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->index(['storage_type_id', 'detail_kind'], 'fk_sf_storage_type');
            $table->index(['terminal_id', 'terminal_type'], 'fk_sf_terminal');
            $table->index(['terminal_id', 'status'], 'idx_sf_terminal_status');
            $table->unique(['id', 'detail_kind'], 'uq_sf_id_kind');
            $table->unique(['id', 'terminal_id'], 'uq_sf_id_terminal');
            $table->unique(['id', 'capacity_unit'], 'uq_sf_id_unit');
            $table->unique(['terminal_id', 'code'], 'uq_sf_terminal_code');

            $table->foreign(['storage_type_id', 'detail_kind'], 'fk_sf_storage_type')->references(['id', 'detail_kind'])->on('storage_types');
            $table->foreign(['terminal_id', 'terminal_type'], 'fk_sf_terminal')->references(['id', 'terminal_type'])->on('terminals');
        });

        DB::statement('ALTER TABLE `storage_facilities` ADD CONSTRAINT `chk_sf_area` CHECK (((area is null) or (area > 0)))');
        DB::statement('ALTER TABLE `storage_facilities` ADD CONSTRAINT `chk_sf_capacity` CHECK ((((capacity is null) or (capacity > 0)) and (current_occupancy >= 0) and (reserved_capacity >= 0) and (blocked_capacity >= 0)))');
        DB::statement('ALTER TABLE `storage_facilities` ADD CONSTRAINT `chk_sf_capacity_sum` CHECK (((capacity is null) or (((current_occupancy + reserved_capacity) + blocked_capacity) <= capacity)))');
        DB::statement('ALTER TABLE `storage_facilities` ADD CONSTRAINT `chk_sf_floor_load` CHECK (((max_floor_load is null) or (max_floor_load > 0)))');
        DB::statement('ALTER TABLE `storage_facilities` ADD CONSTRAINT `chk_sf_lat` CHECK (((latitude is null) or (latitude between -(90) and 90)))');
        DB::statement('ALTER TABLE `storage_facilities` ADD CONSTRAINT `chk_sf_lon` CHECK (((longitude is null) or (longitude between -(180) and 180)))');
        DB::statement('ALTER TABLE `storage_facilities` ADD CONSTRAINT `chk_sf_yard_rules` CHECK ((((detail_kind = \'CONTAINER_YARD\') and (terminal_type = \'CONTAINER\') and (capacity is null) and (capacity_unit is null) and (current_occupancy = 0) and (reserved_capacity = 0) and (blocked_capacity = 0)) or ((detail_kind <> \'CONTAINER_YARD\') and (capacity is not null) and (capacity_unit is not null))))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_facilities');
    }
};
