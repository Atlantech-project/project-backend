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
        Schema::create('reefer_zone_details', function (Blueprint $table) {
            $table->unsignedBigInteger('yard_zone_id')->primary();
            $table->enum('zone_detail', ['NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG'])->default('REEFER');
            $table->integer('total_power_plugs')->comment('Free plugs = total - reefer containers in the zone (derived)');
            $table->string('power_type', 30)->nullable()->comment('Display only, e.g. 380-460V 3-phase');
            $table->index(['yard_zone_id', 'zone_detail'], 'fk_rzd_zone');

            $table->foreign(['yard_zone_id', 'zone_detail'], 'fk_rzd_zone')->references(['id', 'zone_detail'])->on('yard_zones');
        });

        DB::statement('ALTER TABLE `reefer_zone_details` ADD CONSTRAINT `chk_rzd_plugs` CHECK ((total_power_plugs > 0))');
        DB::statement('ALTER TABLE `reefer_zone_details` ADD CONSTRAINT `chk_rzd_type` CHECK ((zone_detail = \'REEFER\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reefer_zone_details');
    }
};
