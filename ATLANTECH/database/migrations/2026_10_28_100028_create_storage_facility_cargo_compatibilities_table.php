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
        Schema::create('storage_facility_cargo_compatibilities', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_facility_id');
            $table->unsignedBigInteger('terminal_id');
            $table->unsignedBigInteger('cargo_type_id');
            $table->primary(['storage_facility_id', 'cargo_type_id']);
            $table->index(['storage_facility_id', 'terminal_id'], 'fk_sfcc_facility');
            $table->index(['terminal_id', 'cargo_type_id'], 'fk_sfcc_terminal_cargo');

            $table->foreign(['storage_facility_id', 'terminal_id'], 'fk_sfcc_facility')->references(['id', 'terminal_id'])->on('storage_facilities');
            $table->foreign(['terminal_id', 'cargo_type_id'], 'fk_sfcc_terminal_cargo')->references(['terminal_id', 'cargo_type_id'])->on('terminal_cargo_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_facility_cargo_compatibilities');
    }
};
