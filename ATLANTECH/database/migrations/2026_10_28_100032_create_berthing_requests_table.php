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
        Schema::create('berthing_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vessel_id');
            $table->unsignedBigInteger('requested_terminal_id');
            $table->enum('source', ['MANUAL', 'EXTERNAL']);
            $table->unsignedBigInteger('external_system_id')->nullable()->comment('Required when EXTERNAL. Optional for MANUAL: staff enter the EGMSW trip number (seed external_system row EGMSW)');
            $table->string('external_reference', 100)->nullable()->comment('Pairs with external_system_id (both set or both empty for MANUAL); unique per external system');
            $table->unsignedBigInteger('submitted_by')->nullable()->comment('Required when MANUAL; NULL when ingested by the external adapter');
            $table->dateTime('requested_eta')->comment('UTC');
            $table->decimal('arrival_draft', 5, 2)->comment('m, this call; backend checks > 0 and <= vessel.max_draft');
            $table->decimal('departure_draft', 5, 2)->nullable()->comment('m, expected draft on departure. Nullable (not known at arrival)');
            $table->char('previous_port', 5)->nullable()->comment('UN/LOCODE, nullable');
            $table->char('next_port', 5)->nullable()->comment('UN/LOCODE, nullable');
            $table->enum('draft_source', ['MANUAL', 'EGMSW_FEED', 'REGISTRY'])->default('MANUAL');
            $table->enum('eta_source', ['MANUAL', 'EGMSW_FEED', 'REGISTRY'])->default('MANUAL');
            $table->enum('ports_source', ['MANUAL', 'EGMSW_FEED', 'REGISTRY'])->default('MANUAL');
            $table->decimal('block_coefficient', 4, 3)->comment('Cb entered on the request form (D-44)');
            $table->integer('gross_tonnage')->comment('GT, entered on the request form; input to the AI tug formula (OQ-15-1)');
            $table->integer('deadweight_tonnes')->comment('DWT (tonnes), entered on the request form; input to the AI tug formula (OQ-15-1)');
            $table->enum('status', ['SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED'])->default('SUBMITTED');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()->comment('Version token for the 409 check (microseconds)');
            $table->index(['submitted_by'], 'fk_br_user');
            $table->index(['vessel_id'], 'fk_br_vessel');
            $table->index(['status'], 'idx_br_status');
            $table->index(['requested_terminal_id', 'status'], 'idx_br_terminal_status');
            $table->unique(['external_system_id', 'external_reference'], 'uq_br_external_ref');
            $table->unique(['id', 'requested_terminal_id'], 'uq_br_id_terminal');
            $table->unique(['id', 'vessel_id'], 'uq_br_id_vessel');
            $table->index(['vessel_id', 'requested_eta'], 'idx_br_vessel_eta');

            $table->foreign('external_system_id', 'fk_br_external')->references('id')->on('external_systems');
            $table->foreign('requested_terminal_id', 'fk_br_terminal')->references('id')->on('terminals');
            $table->foreign('submitted_by', 'fk_br_user')->references('id')->on('users');
            $table->foreign('vessel_id', 'fk_br_vessel')->references('id')->on('vessels');

            $table->comment('status = current state; history in berthing_request_status_history (same transaction). Return for revision only from SUBMITTED; ENTRY_WINDOW_CONFIRMED may be reopened to CUSTOMS_APPROVED while no plan is confirmed. Cancel only before Preliminary Approval.');
        });

        DB::statement('ALTER TABLE `berthing_requests` ADD CONSTRAINT `chk_br_cb` CHECK ((block_coefficient > 0.3) and (block_coefficient < 1.0))');
        DB::statement('ALTER TABLE `berthing_requests` ADD CONSTRAINT `chk_br_tonnage` CHECK ((gross_tonnage > 0) and (deadweight_tonnes > 0))');
        DB::statement('ALTER TABLE `berthing_requests` ADD CONSTRAINT `chk_br_source` CHECK ((source = \'EXTERNAL\' AND external_system_id IS NOT NULL AND external_reference IS NOT NULL) OR (source = \'MANUAL\' AND submitted_by IS NOT NULL AND (external_system_id IS NULL) = (external_reference IS NULL)))');
        DB::statement('ALTER TABLE `berthing_requests` ADD CONSTRAINT `chk_br_departure_draft` CHECK (departure_draft IS NULL OR departure_draft > 0)');
        DB::statement('ALTER TABLE `berthing_requests` ADD CONSTRAINT `chk_br_ports` CHECK ((previous_port IS NULL OR regexp_like(previous_port, \'^[A-Z]{2}[A-Z2-9]{3}$\', \'c\')) AND (next_port IS NULL OR regexp_like(next_port, \'^[A-Z]{2}[A-Z2-9]{3}$\', \'c\')))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('berthing_requests');
    }
};
