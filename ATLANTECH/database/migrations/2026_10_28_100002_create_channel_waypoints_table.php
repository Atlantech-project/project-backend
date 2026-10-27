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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_waypoints');
    }
};
