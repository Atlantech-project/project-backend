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
        Schema::create('yard_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('yard_zone_id');
            $table->string('code', 30);
            $table->unique(['yard_zone_id', 'code'], 'uq_yb_zone_code');

            $table->foreign('yard_zone_id', 'fk_yb_zone')->references('id')->on('yard_zones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yard_blocks');
    }
};
