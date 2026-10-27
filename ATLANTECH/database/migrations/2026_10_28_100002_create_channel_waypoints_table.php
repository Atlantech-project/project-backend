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
        Schema::create('channel_waypoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->integer('sequence_order');
            $table->string('label', 60)->comment('Waypoint name entered by admin, e.g. Fairway Buoy');
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->unique(['channel_id', 'label'], 'uq_channel_waypoint_label');
            $table->unique(['channel_id', 'sequence_order'], 'uq_channel_waypoint_order');

            $table->foreign('channel_id', 'fk_channel_waypoint_channel')->references('id')->on('navigation_channels');
        });

        DB::statement('ALTER TABLE `channel_waypoints` ADD CONSTRAINT `chk_waypoint_lat` CHECK ((latitude between -(90) and 90))');
        DB::statement('ALTER TABLE `channel_waypoints` ADD CONSTRAINT `chk_waypoint_lon` CHECK ((longitude between -(180) and 180))');
        DB::statement('ALTER TABLE `channel_waypoints` ADD CONSTRAINT `chk_waypoint_order` CHECK ((sequence_order > 0))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_waypoints');
    }
};
