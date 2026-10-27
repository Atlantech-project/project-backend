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
        Schema::create('recommendation_option_tugs', function (Blueprint $table) {
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('tugboat_id');
            $table->primary(['option_id', 'tugboat_id']);
            $table->index(['tugboat_id'], 'fk_rot_tug');

            $table->foreign('option_id', 'fk_rot_option')->references('id')->on('recommendation_options');
            $table->foreign('tugboat_id', 'fk_rot_tug')->references('id')->on('tugboats');

            $table->comment('Number of tugs decided by the AI formula.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_option_tugs');
    }
};
