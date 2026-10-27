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
        Schema::create('recommendation_rationale_factors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('option_id');
            $table->enum('factor_type', ['CONSTRAINT', 'OBJECTIVE_TERM']);
            $table->unsignedBigInteger('constraint_type_id')->nullable();
            $table->unsignedBigInteger('objective_term_id')->nullable();
            $table->decimal('factor_value', 14, 4)->nullable();
            $table->integer('display_order')->default(0);
            $table->index(['constraint_type_id'], 'fk_rrf_constraint');
            $table->index(['objective_term_id'], 'fk_rrf_objective');
            $table->index(['option_id'], 'fk_rrf_option');

            $table->foreign('constraint_type_id', 'fk_rrf_constraint')->references('id')->on('constraint_types');
            $table->foreign('objective_term_id', 'fk_rrf_objective')->references('id')->on('objective_terms');
            $table->foreign('option_id', 'fk_rrf_option')->references('id')->on('recommendation_options');

            $table->comment('Constraints/objective terms that actually drove the solve (FR-1.4.10).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_rationale_factors');
    }
};
