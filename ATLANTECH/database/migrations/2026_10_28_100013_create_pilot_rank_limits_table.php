<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()->comment('Version token for the 409 check (microseconds)');

            $table->comment('Settings screen: one row per rank.');
        });

        DB::statement('ALTER TABLE `pilot_rank_limits` ADD CONSTRAINT `chk_pilot_rank_limit_loa` CHECK (((max_vessel_loa is null) or (max_vessel_loa > 0)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilot_rank_limits');
    }
};
