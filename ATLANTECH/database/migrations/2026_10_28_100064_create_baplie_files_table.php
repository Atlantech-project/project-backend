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
        Schema::create('baplie_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vessel_id');
            $table->unsignedBigInteger('berthing_request_id')->comment('Must be a request for the same vessel (composite FK below)');
            $table->unsignedBigInteger('uploaded_by');
            $table->char('file_hash_sha256', 64)->comment('Not globally unique: the same file may be uploaded for a different request');
            $table->char('live_hash_key', 64)->nullable()->storedAs('IF(`status` = \'WITHDRAWN\', NULL, `file_hash_sha256`)')->comment('GENERATED (stored): IF(status = WITHDRAWN, NULL, file_hash_sha256)');
            $table->string('storage_path', 255);
            $table->enum('parse_status', ['PENDING', 'PARSED', 'FAILED', 'QUARANTINED'])->default('PENDING');
            $table->enum('status', ['STAGED', 'ACTIVE', 'REPLACED', 'WITHDRAWN'])->default('STAGED')->comment('STAGED = uploaded, not yet in use; ACTIVE = in use; REPLACED = replaced by a revision; WITHDRAWN = uploaded by mistake');
            $table->unsignedBigInteger('active_request_key')->nullable()->storedAs('IF(`status` = \'ACTIVE\', `berthing_request_id`, NULL)')->unique('active_request_key')->comment('GENERATED (stored): IF(status = ACTIVE, berthing_request_id, NULL). One ACTIVE file per request.');
            $table->json('staged_payload')->nullable()->comment('Quarantine: extracted data of a QUARANTINED file, kept so staff can correct it. Commercially sensitive (terminal isolation rules)');
            $table->boolean('is_manually_corrected')->default(false);
            $table->unsignedBigInteger('corrected_by')->nullable();
            $table->dateTime('corrected_at')->nullable();
            $table->dateTime('uploaded_at')->useCurrent();
            $table->index(['berthing_request_id', 'vessel_id'], 'fk_bf_request');
            $table->index(['uploaded_by'], 'fk_bf_user');
            $table->index(['vessel_id'], 'fk_bf_vessel');
            $table->index(['corrected_by'], 'fk_bf_corrected_by');
            $table->index(['berthing_request_id', 'uploaded_at'], 'idx_bf_request');
            $table->unique(['berthing_request_id', 'live_hash_key'], 'uq_bf_request_live_hash');

            $table->foreign(['berthing_request_id', 'vessel_id'], 'fk_bf_request')->references(['id', 'vessel_id'])->on('berthing_requests');
            $table->foreign('uploaded_by', 'fk_bf_user')->references('id')->on('users');
            $table->foreign('vessel_id', 'fk_bf_vessel')->references('id')->on('vessels');
            $table->foreign('corrected_by', 'fk_cb_user')->references('id')->on('users');

            $table->comment('Exactly one ACTIVE file per request (active_request_key). Replacing a revision: old ACTIVE -> REPLACED, new -> ACTIVE, in one transaction. Mistaken upload -> WITHDRAWN.
Diagnostics rows live in baplie_parse_issue (no JSON copy here).');
        });

        DB::statement('ALTER TABLE `baplie_files` ADD CONSTRAINT `chk_bf_hash` CHECK (regexp_like(file_hash_sha256, \'^[0-9a-f]{64}$\', \'c\'))');
        DB::statement('ALTER TABLE `baplie_files` ADD CONSTRAINT `chk_bf_correction` CHECK ((is_manually_corrected = false) OR (corrected_by IS NOT NULL AND corrected_at IS NOT NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baplie_files');
    }
};
