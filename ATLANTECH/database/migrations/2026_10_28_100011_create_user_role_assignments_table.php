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
        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['PORT_GM', 'PORT_STAFF', 'MARINE_SAFETY_OFFICER', 'PILOTAGE_MANAGER', 'PILOTAGE_OFFICER', 'MARINE_PILOT', 'TUG_MANAGER', 'TUG_OFFICER', 'TUG_MASTER', 'TERMINAL_ADMIN', 'TERMINAL_OFFICER', 'TERMINAL_SUPERVISOR']);
            $table->unsignedBigInteger('organization_unit_id')->comment('Terminal X, Marine Services, or Harbour Authority. Never empty.');
            $table->enum('unit_type', ['HARBOUR_AUTHORITY', 'MARINE_SERVICES', 'TERMINAL'])->comment('Kept equal to organization_unit.unit_type by fk_ura_unit');
            $table->dateTime('active_from')->useCurrent();
            $table->dateTime('active_to')->nullable()->comment('NULL = role active; set when the role is ended (never deleted)');
            $table->tinyInteger('active_assignment_key')->nullable()->storedAs('IF(`active_to` IS NULL, 1, NULL)')->comment('GENERATED (stored): IF(active_to IS NULL, 1, NULL)');
            $table->index(['organization_unit_id', 'unit_type'], 'fk_ura_unit');
            $table->index(['organization_unit_id'], 'idx_ura_unit');
            $table->unique(['user_id', 'role', 'organization_unit_id', 'active_assignment_key'], 'uq_ura_active');

            $table->foreign(['organization_unit_id', 'unit_type'], 'fk_ura_unit')->references(['id', 'unit_type'])->on('organization_units');
            $table->foreign('user_id', 'fk_ura_user')->references('id')->on('users');

            $table->comment('Who holds which role, in which organization unit. Roles are a fixed ENUM (P3-04).');
        });

        DB::statement('ALTER TABLE `user_role_assignments` ADD CONSTRAINT `chk_ura_role_matches_unit` CHECK ((((unit_type = \'HARBOUR_AUTHORITY\') and (role in (\'PORT_GM\',\'PORT_STAFF\',\'MARINE_SAFETY_OFFICER\'))) or ((unit_type = \'MARINE_SERVICES\') and (role in (\'PILOTAGE_MANAGER\',\'PILOTAGE_OFFICER\',\'MARINE_PILOT\',\'TUG_MANAGER\',\'TUG_OFFICER\',\'TUG_MASTER\'))) or ((unit_type = \'TERMINAL\') and (role in (\'TERMINAL_ADMIN\',\'TERMINAL_OFFICER\',\'TERMINAL_SUPERVISOR\')))))');
        DB::statement('ALTER TABLE `user_role_assignments` ADD CONSTRAINT `chk_ura_period` CHECK (`active_to` IS NULL OR `active_to` > `active_from`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
    }
};
