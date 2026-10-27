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
        Schema::create('equipment_capabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('capability_type_id');
            $table->decimal('capability_value', 12, 3)->comment('In capability_type.unit');
            $table->index(['capability_type_id'], 'fk_ecap_type');
            $table->unique(['equipment_id', 'capability_type_id'], 'uq_ecap_equipment_type');

            $table->foreign('equipment_id', 'fk_ecap_equipment')->references('id')->on('equipment');
            $table->foreign('capability_type_id', 'fk_ecap_type')->references('id')->on('capability_types');
        });

        DB::statement('ALTER TABLE `equipment_capabilities` ADD CONSTRAINT `chk_ecap_value` CHECK ((capability_value >= 0))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_capabilities');
    }
};
