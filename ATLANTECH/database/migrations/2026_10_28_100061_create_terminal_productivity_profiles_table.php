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
        Schema::create('terminal_productivity_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('terminal_id');
            $table->enum('terminal_type', ['CONTAINER', 'NON_CONTAINER'])->comment('Copy of terminal.terminal_type, enforced by composite FK');
            $table->unsignedBigInteger('vessel_type_id')->nullable()->comment('NULL = all vessel types (terminal default)');
            $table->decimal('productivity_rate', 10, 2);
            $table->enum('rate_unit', ['MOVES_PER_HOUR', 'TONNES_PER_HOUR', 'CUBIC_METRES_PER_HOUR', 'UNITS_PER_HOUR']);
            $table->integer('fixed_overhead_minutes');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->index(['terminal_id', 'terminal_type'], 'fk_tpp_terminal');
            $table->index(['vessel_type_id'], 'fk_tpp_vtype');
            $table->index(['terminal_id', 'vessel_type_id', 'valid_from'], 'idx_tpp_lookup');

            $table->foreign(['terminal_id', 'terminal_type'], 'fk_tpp_terminal')->references(['id', 'terminal_type'])->on('terminals');
            $table->foreign('vessel_type_id', 'fk_tpp_vtype')->references('id')->on('vessel_types');

            $table->comment('Inputs for the AI service-time calculation (Module 15). Overlapping periods rejected by the backend.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_productivity_profiles');
    }
};
