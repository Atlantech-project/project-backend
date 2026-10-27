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
        Schema::create('tide_constituents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('constituent_set_id');
            $table->string('constituent_code', 10)->comment('M2, S2, K1, O1 ...');
            $table->decimal('amplitude', 8, 4)->comment('m');
            $table->decimal('phase_lag', 7, 3)->comment('degrees');
            $table->decimal('speed_deg_per_hour', 10, 6);
            $table->unique(['constituent_set_id', 'constituent_code'], 'uq_tc_set_code');

            $table->foreign('constituent_set_id', 'fk_tc_set')->references('id')->on('tide_constituent_sets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tide_constituents');
    }
};
