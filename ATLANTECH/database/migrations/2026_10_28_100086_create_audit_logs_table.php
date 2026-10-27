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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->comment('NULL = system / AI worker');
            $table->string('entity_type', 60)->comment('Table name, e.g. safety_constraint, user_role_assignment');
            $table->unsignedBigInteger('entity_id')->nullable()->comment('NULL when a refused attempt has no entity (e.g. failed login)');
            $table->enum('action', ['CREATE', 'UPDATE', 'DELETE', 'STATUS_CHANGE', 'SAFETY_EXCEPTION', 'REJECTED_ATTEMPT']);
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->dateTime('occurred_at')->useCurrent();
            $table->index(['actor_id'], 'fk_al_user');
            $table->index(['entity_type', 'entity_id'], 'idx_al_entity');
            $table->index(['occurred_at'], 'idx_al_time');

            $table->foreign('actor_id', 'fk_al_user')->references('id')->on('users');

            $table->comment('Append-only: the application DB user gets INSERT + SELECT only on this table (P21-03).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
