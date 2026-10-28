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
        Schema::create('pilot_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->enum('pilot_rank', ['FIRST', 'SECOND', 'THIRD'])->comment('FIRST = most senior');
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()->comment('Version token for the 409 check (microseconds)');

            $table->foreign('user_id', 'fk_pilot_profile_user')->references('id')->on('users');

            $table->comment('1:1 with user. App rule: user must hold MARINE_PILOT in MARINE_SERVICES (D-29).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilot_profiles');
    }
};
