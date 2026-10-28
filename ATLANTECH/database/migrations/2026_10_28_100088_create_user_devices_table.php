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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_id', 100);
            $table->enum('platform', ['ANDROID', 'IOS']);
            $table->string('app_version', 20);
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('revoked_at')->nullable()->comment('Never deleted: set to revoke');
            $table->string('push_token', 255)->nullable()->comment('FCM registration token; NULL = no push for this device');
            $table->dateTime('push_token_updated_at')->nullable()->comment('Last registration or refresh of push_token');
            $table->unique(['user_id', 'device_id'], 'uq_user_device');
            $table->unique(['push_token'], 'uq_user_device_push_token');

            $table->foreign('user_id', 'fk_device_user')->references('id')->on('users');

            $table->comment('Never deleted: revoked_at is set instead.');
        });

        DB::statement('ALTER TABLE `user_devices` ADD CONSTRAINT `chk_ud_push_revoked` CHECK ((revoked_at is null) or (push_token is null))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
