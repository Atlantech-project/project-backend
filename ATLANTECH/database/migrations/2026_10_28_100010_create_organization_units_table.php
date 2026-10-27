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
        Schema::create('organization_units', function (Blueprint $table) {
            $table->id();
            $table->enum('unit_type', ['HARBOUR_AUTHORITY', 'MARINE_SERVICES', 'TERMINAL']);
            $table->unsignedBigInteger('terminal_id')->nullable()->comment('Set only for TERMINAL units; display name comes from terminal.name');
            $table->unsignedBigInteger('single_key')->nullable()->comment('GENERATED (stored): coalesce(terminal_id,0)');
            $table->index(['terminal_id'], 'fk_org_unit_terminal');
            $table->unique(['id', 'unit_type'], 'uq_org_unit_id_type');
            $table->unique(['unit_type', 'single_key'], 'uq_org_unit_type_key');

            $table->foreign('terminal_id', 'fk_org_unit_terminal')->references('id')->on('terminals');

            $table->comment('Where a user works: the Harbour Authority, Marine Services, or one terminal. A TERMINAL row is created with each terminal.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};
