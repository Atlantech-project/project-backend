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
        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['PORT_GM', 'PORT_STAFF', 'MARINE_SAFETY_OFFICER', 'PILOTAGE_MANAGER', 'PILOTAGE_OFFICER', 'MARINE_PILOT', 'TUG_MANAGER', 'TUG_OFFICER', 'TUG_MASTER', 'TERMINAL_ADMIN', 'TERMINAL_OFFICER', 'TERMINAL_SUPERVISOR']);
            $table->unsignedBigInteger('organization_unit_id')->comment('Terminal X, Marine Services, or Harbour Authority. Never empty.');
            $table->enum('unit_type', ['HARBOUR_AUTHORITY', 'MARINE_SERVICES', 'TERMINAL'])->comment('Kept equal to organization_unit.unit_type by fk_ura_unit');
            $table->dateTime('active_from')->useCurrent();
            $table->dateTime('active_to')->nullable()->comment('NULL = role active; set when the role is ended (never deleted)');
            $table->tinyInteger('active_assignment_key')->nullable()->comment('GENERATED (stored): IF(active_to IS NULL, 1, NULL)');
            $table->index(['organization_unit_id', 'unit_type'], 'fk_ura_unit');
            $table->index(['organization_unit_id'], 'idx_ura_unit');
            $table->unique(['user_id', 'role', 'organization_unit_id', 'active_assignment_key'], 'uq_ura_active');

            $table->foreign(['organization_unit_id', 'unit_type'], 'fk_ura_unit')->references(['id', 'unit_type'])->on('organization_units');
            $table->foreign('user_id', 'fk_ura_user')->references('id')->on('users');

            $table->comment('Who holds which role, in which organization unit. Roles are a fixed ENUM (P3-04).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
    }
};
