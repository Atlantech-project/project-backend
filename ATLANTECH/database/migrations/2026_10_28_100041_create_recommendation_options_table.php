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
        Schema::create('recommendation_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recommendation_id');
            $table->unsignedBigInteger('terminal_id')->comment('Copy, enforced by composite FKs: berth must be allowed for the requested terminal (berth_terminal)');
            $table->integer('option_rank');
            $table->unsignedBigInteger('berth_id');
            $table->dateTime('planned_start');
            $table->dateTime('planned_end');
            $table->unsignedBigInteger('pilot_user_id')->nullable();
            $table->enum('service_time_method', ['FORMULA', 'ML']);
            $table->decimal('service_time_p50_hours', 6, 2);
            $table->decimal('service_time_p90_hours', 6, 2)->nullable()->comment('ML only');
            $table->decimal('delay_risk', 4, 3)->nullable();
            $table->decimal('objective_score', 14, 4)->nullable();
            $table->unsignedBigInteger('rationale_template_id')->nullable();
            $table->index(['berth_id', 'terminal_id'], 'fk_ro_berth');
            $table->index(['pilot_user_id'], 'fk_ro_pilot');
            $table->index(['recommendation_id', 'terminal_id'], 'fk_ro_rec');
            $table->index(['rationale_template_id'], 'fk_ro_template');
            $table->index(['berth_id', 'planned_start'], 'idx_ro_berth_time');
            $table->unique(['id', 'recommendation_id'], 'uq_ro_id_rec');
            $table->unique(['recommendation_id', 'option_rank'], 'uq_ro_rank');

            $table->foreign(['berth_id', 'terminal_id'], 'fk_ro_berth')->references(['berth_id', 'terminal_id'])->on('berth_terminals');
            $table->foreign('pilot_user_id', 'fk_ro_pilot')->references('user_id')->on('pilot_profiles');
            $table->foreign(['recommendation_id', 'terminal_id'], 'fk_ro_rec')->references(['id', 'terminal_id'])->on('recommendations');
            $table->foreign('rationale_template_id', 'fk_ro_template')->references('id')->on('rationale_templates');

            $table->comment('No is_selected column: the selected option is the one operational_plan references (backend reads it, P15-03).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_options');
    }
};
