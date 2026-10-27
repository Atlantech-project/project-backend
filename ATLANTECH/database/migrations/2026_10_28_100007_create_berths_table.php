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
        Schema::create('berths', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80)->nullable();
            $table->decimal('length', 7, 2)->comment('metres');
            $table->decimal('water_depth', 6, 2)->comment('Water depth alongside the berth (m) below depth_datum');
            $table->string('depth_datum', 40)->comment('e.g. LAT; must equal navigation_channel.depth_datum and the active tide datum');
            $table->decimal('max_vessel_loa', 7, 2)->comment('metres');
            $table->enum('status', ['ACTIVE', 'MAINTENANCE', 'INACTIVE'])->default('ACTIVE')->comment('Administrative only. Reserved/occupied is derived from plan_component and actual status');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->index(['status'], 'idx_berth_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('berths');
    }
};
