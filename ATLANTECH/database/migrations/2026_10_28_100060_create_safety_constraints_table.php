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
        Schema::create('safety_constraints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('constraint_type_id');
            $table->unsignedBigInteger('terminal_id')->nullable()->comment('NULL = port-wide');
            $table->unsignedBigInteger('berth_id')->nullable()->comment('NULL = whole terminal / port; if set, terminal_id is the berth\'s terminal');
            $table->unsignedBigInteger('vessel_type_id')->nullable()->comment('NULL = all vessel types');
            $table->string('imdg_class', 3)->nullable()->comment('NULL = any cargo');
            $table->unsignedBigInteger('equipment_category_id')->nullable()->comment('NULL = not equipment-specific (e.g. cranes stop above a wind limit)');
            $table->decimal('value', 12, 3)->comment('In constraint_type.unit');
            $table->unsignedBigInteger('created_by');
            $table->dateTime('active_from')->comment('UTC');
            $table->dateTime('active_to')->nullable()->comment('UTC; NULL = open-ended');
            $table->index(['berth_id', 'terminal_id'], 'fk_sc_berth_terminal');
            $table->index(['equipment_category_id'], 'fk_sc_eqcat');
            $table->index(['imdg_class'], 'fk_sc_imdg');
            $table->index(['terminal_id'], 'fk_sc_terminal');
            $table->index(['created_by'], 'fk_sc_user');
            $table->index(['vessel_type_id'], 'fk_sc_vtype');
            $table->index(['constraint_type_id', 'terminal_id', 'berth_id', 'vessel_type_id', 'active_from'], 'idx_sc_lookup');

            $table->foreign('berth_id', 'fk_sc_berth')->references('id')->on('berths');
            $table->foreign(['berth_id', 'terminal_id'], 'fk_sc_berth_terminal')->references(['berth_id', 'terminal_id'])->on('berth_terminals');
            $table->foreign('equipment_category_id', 'fk_sc_eqcat')->references('id')->on('equipment_categories');
            $table->foreign('imdg_class', 'fk_sc_imdg')->references('code')->on('imdg_classes');
            $table->foreign('terminal_id', 'fk_sc_terminal')->references('id')->on('terminals');
            $table->foreign('constraint_type_id', 'fk_sc_type')->references('id')->on('constraint_types');
            $table->foreign('created_by', 'fk_sc_user')->references('id')->on('users');
            $table->foreign('vessel_type_id', 'fk_sc_vtype')->references('id')->on('vessel_types');

            $table->comment('Overlap of the same type + scope is rejected by the backend. Several scopes apply to one case: the STRICTEST value wins (Moustafa, OQ-P9-2).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_constraints');
    }
};
