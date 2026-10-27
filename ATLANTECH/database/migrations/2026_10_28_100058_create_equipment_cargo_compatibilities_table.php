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
        Schema::create('equipment_cargo_compatibilities', function (Blueprint $table) {
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('terminal_id');
            $table->unsignedBigInteger('cargo_type_id');
            $table->primary(['equipment_id', 'cargo_type_id']);
            $table->index(['equipment_id', 'terminal_id'], 'fk_ecc_equipment');
            $table->index(['terminal_id', 'cargo_type_id'], 'fk_ecc_terminal_cargo');

            $table->foreign(['equipment_id', 'terminal_id'], 'fk_ecc_equipment')->references(['id', 'terminal_id'])->on('equipment');
            $table->foreign(['terminal_id', 'cargo_type_id'], 'fk_ecc_terminal_cargo')->references(['terminal_id', 'cargo_type_id'])->on('terminal_cargo_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_cargo_compatibilities');
    }
};
