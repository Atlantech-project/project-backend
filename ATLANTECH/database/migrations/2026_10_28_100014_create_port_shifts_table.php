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
        Schema::create('port_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->time('start_time')->comment('Port local time (port_profile.timezone)');
            $table->time('end_time')->comment('If end_time < start_time the shift ends the next day');
            $table->boolean('is_active')->default(true);

            $table->comment('Fixed port shifts, defined on the settings screen. App rule: active shifts must not overlap.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_shifts');
    }
};
