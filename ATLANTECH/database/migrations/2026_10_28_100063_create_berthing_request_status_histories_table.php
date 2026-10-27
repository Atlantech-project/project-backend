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
        Schema::create('berthing_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('berthing_request_id');
            $table->enum('from_status', ['SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED'])->nullable()->comment('NULL on creation');
            $table->enum('to_status', ['SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED']);
            $table->unsignedBigInteger('changed_by')->nullable()->comment('NULL = system / external adapter');
            $table->dateTime('changed_at')->useCurrent();
            $table->string('note', 500)->nullable();
            $table->index(['changed_by'], 'fk_brsh_user');
            $table->index(['berthing_request_id', 'changed_at'], 'idx_brsh_request');

            $table->foreign('berthing_request_id', 'fk_brsh_request')->references('id')->on('berthing_requests');
            $table->foreign('changed_by', 'fk_brsh_user')->references('id')->on('users');

            $table->comment('CUSTOMS_APPROVED row = who/when Customs Approval was recorded (D1, D4).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('berthing_request_status_histories');
    }
};
