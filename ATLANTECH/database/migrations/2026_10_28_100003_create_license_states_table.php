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
        Schema::create('license_states', function (Blueprint $table) {
            $table->tinyInteger('id')->primary()->default(1);
            $table->text('token')->nullable()->comment('Current signed licence token');
            $table->unsignedBigInteger('license_ref')->nullable()->comment('SaaS license.id (no FK, other DB)');
            $table->string('plan_name', 80)->nullable();
            $table->integer('max_terminals')->nullable();
            $table->integer('max_berths')->nullable();
            $table->integer('max_users')->nullable();
            $table->dateTime('license_expires_at')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->enum('port_status', ['ONBOARDING', 'ACTIVE', 'SUSPENDED', 'OFFBOARDED'])->nullable();
            $table->enum('license_status', ['PENDING', 'ACTIVE', 'SUSPENDED', 'EXPIRED', 'REVOKED'])->nullable();
            $table->dateTime('status_received_at')->nullable();
            $table->dateTime('last_check_in_attempt_at')->nullable();
            $table->dateTime('last_successful_check_in_at')->nullable();
            $table->string('last_check_in_error', 255)->nullable();
            $table->dateTime('max_seen_time')->nullable()->comment('Clock-rollback guard');
            $table->enum('mode', ['SETUP', 'FULL', 'READ_ONLY'])->default('READ_ONLY');
            $table->enum('read_only_reason', ['NONE', 'NO_LICENSE', 'TOKEN_EXPIRED', 'SUSPENDED', 'REVOKED', 'INVALID_SIGNATURE', 'CLOCK_ROLLBACK', 'PORT_SUSPENDED', 'PORT_OFFBOARDED', 'LICENSE_NOT_YET_IN_EFFECT'])->default('NO_LICENSE');
            $table->dateTime('updated_at')->useCurrent();

            $table->comment('Local copy of the SaaS licence (hybrid signed token). Public key and API credential live in config, not here.');
        });

        DB::statement('ALTER TABLE `license_states` ADD CONSTRAINT `chk_license_state_singleton` CHECK ((id = 1))');
        DB::statement('ALTER TABLE `license_states` ADD CONSTRAINT `chk_license_state_reason` CHECK ((`mode` IN (\'FULL\',\'SETUP\')) = (`read_only_reason` = \'NONE\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_states');
    }
};
