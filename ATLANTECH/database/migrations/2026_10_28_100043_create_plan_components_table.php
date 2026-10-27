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
        Schema::create('plan_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operational_plan_id');
            $table->enum('component_type', ['BERTH', 'PILOT', 'TUG', 'TERMINAL_OPS']);
            $table->unsignedBigInteger('berth_id')->nullable();
            $table->unsignedBigInteger('tugboat_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->comment('Pilot / Tug Master / terminal supervisor (role checked by backend, D-33)');
            $table->unsignedBigInteger('assigned_by')->nullable()->comment('Dept. Officer (FR-1.5.1/2)');
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('planned_start');
            $table->dateTime('planned_end');
            $table->enum('reservation_state', ['RESERVED', 'RELEASED', 'CONSUMED'])->default('RESERVED')->comment('RELEASED on plan cancellation');
            $table->index(['assigned_by'], 'fk_pc_assigner');
            $table->index(['operational_plan_id'], 'fk_pc_plan');
            $table->index(['terminal_id'], 'fk_pc_terminal');
            $table->index(['berth_id', 'planned_start'], 'idx_pc_berth_time');
            $table->index(['tugboat_id', 'planned_start'], 'idx_pc_tug_time');
            $table->index(['assigned_user_id', 'planned_start'], 'idx_pc_user_time');
            $table->unique(['id', 'component_type'], 'uq_pc_id_type');

            $table->foreign('assigned_user_id', 'fk_pc_assignee')->references('id')->on('users');
            $table->foreign('assigned_by', 'fk_pc_assigner')->references('id')->on('users');
            $table->foreign('berth_id', 'fk_pc_berth')->references('id')->on('berths');
            $table->foreign('operational_plan_id', 'fk_pc_plan')->references('id')->on('operational_plans');
            $table->foreign('terminal_id', 'fk_pc_terminal')->references('id')->on('terminals');
            $table->foreign('tugboat_id', 'fk_pc_tug')->references('id')->on('tugboats');

            $table->comment('One row per dispatched unit (one per tug). Berth/tug occupancy = RESERVED rows overlapping a window (D-20).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_components');
    }
};
