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
        Schema::create('pilot_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pilot_user_id');
            $table->date('shift_date')->comment('Port local date the shift starts on');
            $table->unsignedBigInteger('port_shift_id');
            $table->unsignedBigInteger('created_by');
            $table->index(['created_by'], 'fk_pilot_schedule_created_by');
            $table->index(['port_shift_id'], 'fk_pilot_schedule_shift');
            $table->index(['shift_date', 'port_shift_id'], 'idx_pilot_schedule_date');
            $table->unique(['pilot_user_id', 'shift_date', 'port_shift_id'], 'uq_pilot_schedule');

            $table->foreign('created_by', 'fk_pilot_schedule_created_by')->references('id')->on('users');
            $table->foreign('pilot_user_id', 'fk_pilot_schedule_pilot')->references('user_id')->on('pilot_profiles');
            $table->foreign('port_shift_id', 'fk_pilot_schedule_shift')->references('id')->on('port_shifts');

            $table->comment('Which pilot works which fixed shift on which day.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilot_schedules');
    }
};
