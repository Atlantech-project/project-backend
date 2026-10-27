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
        Schema::create('deviation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_component_id');
            $table->unsignedBigInteger('status_update_id')->nullable();
            $table->enum('deviation_type', ['DELAY', 'RESOURCE_CHANGED', 'STATE_MISMATCH']);
            $table->string('planned_value', 255);
            $table->string('actual_value', 255);
            $table->integer('delay_minutes')->nullable();
            $table->string('deviation_reason', 500)->nullable();
            $table->dateTime('detected_at')->useCurrent();
            $table->index(['plan_component_id'], 'fk_dl_component');
            $table->index(['status_update_id'], 'fk_dl_update');

            $table->foreign('plan_component_id', 'fk_dl_component')->references('id')->on('plan_components');
            $table->foreign('status_update_id', 'fk_dl_update')->references('id')->on('actual_status_updates');

            $table->comment('P19-03 deferred (2026-10-08): kept as in source for now.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deviation_logs');
    }
};
