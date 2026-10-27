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
        Schema::create('tug_master_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tug_master_user_id');
            $table->date('shift_date')->comment('Port local date the shift starts on');
            $table->unsignedBigInteger('port_shift_id');
            $table->unsignedBigInteger('created_by');
            $table->index(['created_by'], 'fk_tms_created_by');
            $table->index(['port_shift_id'], 'fk_tms_shift');
            $table->index(['shift_date', 'port_shift_id'], 'idx_tug_master_schedule_date');
            $table->unique(['tug_master_user_id', 'shift_date', 'port_shift_id'], 'uq_tug_master_schedule');

            $table->foreign('created_by', 'fk_tms_created_by')->references('id')->on('users');
            $table->foreign('tug_master_user_id', 'fk_tms_master')->references('user_id')->on('tug_master_profiles');
            $table->foreign('port_shift_id', 'fk_tms_shift')->references('id')->on('port_shifts');

            $table->comment('Which tug master works which fixed shift on which day.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tug_master_schedules');
    }
};
