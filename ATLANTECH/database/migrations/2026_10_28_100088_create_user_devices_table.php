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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_id', 100);
            $table->enum('platform', ['ANDROID', 'IOS']);
            $table->string('app_version', 20);
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('revoked_at')->nullable()->comment('Never deleted: set to revoke');
            $table->unique(['user_id', 'device_id'], 'uq_user_device');

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
