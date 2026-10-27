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
        Schema::create('terminal_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recommendation_id');
            $table->unsignedBigInteger('terminal_id');
            $table->enum('terminal_type', ['CONTAINER', 'NON_CONTAINER'])->comment('Copy, enforced by composite FK');
            $table->enum('rec_type', ['TIMING', 'YARD_PLACEMENT', 'STORAGE_ALLOCATION']);
            $table->dateTime('window_start')->nullable();
            $table->dateTime('window_end')->nullable();
            $table->enum('decision', ['PENDING', 'ACCEPTED', 'REJECTED', 'ADJUSTED'])->default('PENDING');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->enum('lifecycle_status', ['YARD_REC_REQUESTED', 'YARD_REC_GENERATED', 'GENERATION_FAILED', 'YARD_REC_ACCEPTED', 'YARD_REC_REJECTED', 'EXPORTED_TO_TOS'])->nullable()->comment('YARD_PLACEMENT only');
            $table->string('failure_reason', 500)->nullable();
            $table->dateTime('exported_at')->nullable();
            $table->unsignedBigInteger('exported_by')->nullable();
            $table->unsignedBigInteger('rationale_template_id')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->index(['recommendation_id', 'terminal_id'], 'fk_tr_rec');
            $table->index(['terminal_id', 'terminal_type'], 'fk_tr_terminal');
            $table->index(['decided_by'], 'fk_tr_user');
            $table->index(['exported_by'], 'fk_tr_exported_by');
            $table->index(['rationale_template_id'], 'fk_tr_template');
            $table->index(['terminal_id', 'decision'], 'idx_tr_terminal_decision');
            $table->unique(['id', 'rec_type'], 'uq_tr_id_type');
            $table->unique(['recommendation_id', 'rec_type'], 'uq_tr_rec_type');

            $table->foreign(['recommendation_id', 'terminal_id'], 'fk_tr_rec')->references(['id', 'terminal_id'])->on('recommendations');
            $table->foreign(['terminal_id', 'terminal_type'], 'fk_tr_terminal')->references(['id', 'terminal_type'])->on('terminals');
            $table->foreign('decided_by', 'fk_tr_user')->references('id')->on('users');
            $table->foreign('exported_by', 'fk_tr_exported_by')->references('id')->on('users');
            $table->foreign('rationale_template_id', 'fk_tr_template')->references('id')->on('rationale_templates');

            $table->comment('Common terminal output contract (FR-3.2.2). Visible to that terminal only (§42).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_recommendations');
    }
};
