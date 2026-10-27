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
        Schema::table('feasible_windows', function (Blueprint $table) {
            $table->foreign('proposal_id', 'fk_fw_proposal')->references('id')->on('entry_window_proposals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feasible_windows', function (Blueprint $table) {
            $table->dropForeign('fk_fw_proposal');
        });
    }
};
