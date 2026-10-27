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
        Schema::create('yard_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_yard_id')->comment('storage_facility.id of a CONTAINER_YARD');
            $table->enum('detail_kind', ['NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK'])->default('CONTAINER_YARD')->comment('Same ENUM as storage_facility (FK compares ENUM index)');
            $table->unsignedBigInteger('yard_zone_type_id');
            $table->enum('zone_detail', ['NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG'])->comment('Copy of yard_zone_type.zone_detail, enforced by composite FK');
            $table->string('code', 30);
            $table->index(['yard_zone_type_id', 'zone_detail'], 'fk_yz_type');
            $table->index(['container_yard_id', 'detail_kind'], 'fk_yz_yard');
            $table->unique(['id', 'zone_detail'], 'uq_yz_id_detail');
            $table->unique(['container_yard_id', 'code'], 'uq_yz_yard_code');

            $table->foreign(['yard_zone_type_id', 'zone_detail'], 'fk_yz_type')->references(['id', 'zone_detail'])->on('yard_zone_types');
            $table->foreign(['container_yard_id', 'detail_kind'], 'fk_yz_yard')->references(['id', 'detail_kind'])->on('storage_facilities');

            $table->comment('Capacity/occupancy are derived from yard_slot.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yard_zones');
    }
};
