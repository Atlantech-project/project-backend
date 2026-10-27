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
        Schema::create('berth_terminals', function (Blueprint $table) {
            $table->unsignedBigInteger('berth_id');
            $table->unsignedBigInteger('terminal_id');
            $table->primary(['berth_id', 'terminal_id']);
            $table->index(['terminal_id'], 'idx_bt_terminal');

            $table->foreign('berth_id', 'fk_bt_berth')->references('id')->on('berths');
            $table->foreign('terminal_id', 'fk_bt_terminal')->references('id')->on('terminals');

            $table->comment('One row per terminal allowed to use the berth. Normal berth = 1 row, shared berth = more. Backend: every berth has at least one row.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('berth_terminals');
    }
};
