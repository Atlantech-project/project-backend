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
        Schema::create('actual_status_updates', function (Blueprint $table) {
            $table->id();
            $table->char('client_event_id', 36)->unique('client_event_id')->comment('UUID generated on the device; duplicates from offline re-sends are ignored');
            $table->unsignedBigInteger('plan_component_id');
            $table->enum('component_type', ['BERTH', 'PILOT', 'TUG', 'TERMINAL_OPS'])->comment('Copy of the component type, enforced by composite FK');
            $table->unsignedBigInteger('reported_by');
            $table->string('status', 30)->comment('Must be a status of this component type (status_type)');
            $table->dateTime('reported_at')->comment('Time on the ground (device)');
            $table->dateTime('client_sent_at')->nullable()->comment('Device clock when the batch was sent');
            $table->dateTime('received_at')->useCurrent()->comment('Server receipt (offline sync)');
            $table->boolean('clock_skew_suspect')->default(false)->comment('1 = received_at - client_sent_at > 5 min; excluded from evaluation');
            $table->enum('sync_source', ['MOBILE', 'TABLET', 'WEB', 'EXTERNAL']);
            $table->index(['plan_component_id', 'component_type'], 'fk_asu_component');
            $table->index(['component_type', 'status'], 'fk_asu_status');
            $table->index(['reported_by'], 'fk_asu_user');
            $table->index(['plan_component_id', 'reported_at'], 'idx_asu_component_time');

            $table->foreign(['plan_component_id', 'component_type'], 'fk_asu_component')->references(['id', 'component_type'])->on('plan_components');
            $table->foreign(['component_type', 'status'], 'fk_asu_status')->references(['component_type', 'code'])->on('status_types');
            $table->foreign('reported_by', 'fk_asu_user')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actual_status_updates');
    }
};
