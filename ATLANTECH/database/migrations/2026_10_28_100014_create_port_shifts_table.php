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
        Schema::create('port_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique('code');
            $table->string('name', 60);
            $table->time('start_time')->comment('Port local time (port_profile.timezone)');
            $table->time('end_time')->comment('If end_time < start_time the shift ends the next day');
            $table->boolean('is_active')->default(true);
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()->comment('Version token for the 409 check (microseconds)');

            $table->comment('Fixed port shifts, defined on the settings screen. App rule: active shifts must not overlap.');
        });

        DB::statement('ALTER TABLE `port_shifts` ADD CONSTRAINT `chk_port_shift_times` CHECK ((end_time <> start_time))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_shifts');
    }
};
