<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * P1-C3: Sanctum's mobile tokens (CT-04, FR-AC-06). Sanctum's columns, plus the device the token belongs to (lead, 2026-11-01:
 * `user_device_id`, used by P2-E3 to revoke a device) and `revoked_at`, because a token is never deleted (FR-GL-03, Coding Notes §6.3).
 * A framework table like `sessions`: workers get no grant.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->unsignedBigInteger('user_device_id')->comment('The device the token was issued to');
            $table->text('name')->comment('The device id (X-Device-Id) at sign-in');
            $table->string('token', 64)->unique('uq_pat_token');
            $table->text('abilities')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable()->index('idx_pat_expires_at')->comment('30 days after the last use (sliding)');
            $table->dateTime('revoked_at')->nullable()->comment('Never deleted: set to revoke');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('user_device_id', 'fk_pat_user_device')->references('id')->on('user_devices');

            $table->comment('Mobile device tokens (Sanctum). Never deleted: revoked_at is set instead.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
