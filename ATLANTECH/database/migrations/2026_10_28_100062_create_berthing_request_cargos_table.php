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
        Schema::create('berthing_request_cargos', function (Blueprint $table) {
            $table->unsignedBigInteger('berthing_request_id');
            $table->unsignedBigInteger('terminal_id')->comment('Copy of the request terminal, enforced by composite FK');
            $table->unsignedBigInteger('cargo_type_id');
            $table->enum('operation', ['DISCHARGE', 'LOAD']);
            $table->decimal('quantity', 14, 2);
            $table->enum('quantity_unit', ['TONNES', 'CUBIC_METRES', 'UNITS']);
            $table->primary(['berthing_request_id', 'cargo_type_id', 'operation']);
            $table->index(['berthing_request_id', 'terminal_id'], 'fk_brc_request');
            $table->index(['terminal_id', 'cargo_type_id'], 'fk_brc_terminal_cargo');

            $table->foreign(['berthing_request_id', 'terminal_id'], 'fk_brc_request')->references(['id', 'requested_terminal_id'])->on('berthing_requests');
            $table->foreign(['terminal_id', 'cargo_type_id'], 'fk_brc_terminal_cargo')->references(['terminal_id', 'cargo_type_id'])->on('terminal_cargo_types');

            $table->comment('Cargo of non-container calls (container calls use the BAPLIE manifest).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('berthing_request_cargos');
    }
};
