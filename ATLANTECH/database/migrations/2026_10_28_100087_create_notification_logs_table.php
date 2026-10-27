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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('related_entity_type', ['PLAN_COMPONENT', 'BERTHING_REQUEST', 'RECOMMENDATION', 'ENTRY_WINDOW_PROPOSAL', 'TUGBOAT', 'RETRAINING_RUN', 'LICENSE']);
            $table->unsignedBigInteger('related_entity_id');
            $table->enum('type', ['PLAN_ASSIGNMENT', 'PRE_EXECUTION_REMINDER', 'TUG_STILL_BUSY', 'ENTRY_WINDOW_READY', 'RECOMMENDATION_READY', 'PLAN_CANCELLED', 'RETRAINING_REVIEW_NEEDED', 'LICENSE_EXPIRY_WARNING', 'ACK_ESCALATION', 'YARD_RECOMMENDATION_READY', 'YARD_RECOMMENDATION_FAILED', 'DRIFT_ALERT']);
            $table->enum('channel', ['WEB', 'MOBILE']);
            $table->enum('status', ['SENT', 'FAILED', 'ACKNOWLEDGED']);
            $table->string('failure_reason', 255)->nullable();
            $table->dateTime('sent_at')->useCurrent();
            $table->dateTime('acknowledged_at')->nullable();
            $table->index(['related_entity_type', 'related_entity_id'], 'idx_nl_entity');
            $table->index(['user_id', 'status'], 'idx_nl_user_status');

            $table->foreign('user_id', 'fk_nl_user')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
