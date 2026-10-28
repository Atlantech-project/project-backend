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
        Schema::create('tug_master_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedBigInteger('assigned_tugboat_id')->nullable()->unique('assigned_tugboat_id')->comment('NULL = explicitly unassigned; UNIQUE = one master per tug (FR-1.1.2)');
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()->comment('Version token for the 409 check (microseconds)');

            $table->foreign('assigned_tugboat_id', 'fk_tug_master_tugboat')->references('id')->on('tugboats');
            $table->foreign('user_id', 'fk_tug_master_user')->references('id')->on('users');

            $table->comment('1:1 with user. App rule: user must hold TUG_MASTER in MARINE_SERVICES (D-29).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tug_master_profiles');
    }
};
