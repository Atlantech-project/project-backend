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
        Schema::create('pilot_rank_limits', function (Blueprint $table) {
            $table->enum('pilot_rank', ['FIRST', 'SECOND', 'THIRD'])->primary();
            $table->decimal('max_vessel_loa', 7, 2)->nullable()->comment('Largest vessel LOA (m) this rank may pilot; NULL = no limit');

            $table->comment('Settings screen: one row per rank.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilot_rank_limits');
    }
};
