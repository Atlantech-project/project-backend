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
        Schema::create('terminal_cargo_types', function (Blueprint $table) {
            $table->unsignedBigInteger('terminal_id');
            $table->unsignedBigInteger('cargo_type_id');
            $table->primary(['terminal_id', 'cargo_type_id']);
            $table->index(['cargo_type_id'], 'fk_tct_cargo_type');

            $table->foreign('cargo_type_id', 'fk_tct_cargo_type')->references('id')->on('cargo_types');
            $table->foreign('terminal_id', 'fk_tct_terminal')->references('id')->on('terminals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_cargo_types');
    }
};
