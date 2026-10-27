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
        Schema::create('dg_zone_allowed_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('yard_zone_id');
            $table->enum('zone_detail', ['NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG'])->default('DANGEROUS_GOODS');
            $table->string('imdg_class', 3);
            $table->primary(['yard_zone_id', 'imdg_class']);
            $table->index(['imdg_class'], 'fk_dzac_imdg');
            $table->index(['yard_zone_id', 'zone_detail'], 'fk_dzac_zone');

            $table->foreign('imdg_class', 'fk_dzac_imdg')->references('code')->on('imdg_classes');
            $table->foreign(['yard_zone_id', 'zone_detail'], 'fk_dzac_zone')->references(['id', 'zone_detail'])->on('yard_zones');
        });

        DB::statement('ALTER TABLE `dg_zone_allowed_classes` ADD CONSTRAINT `chk_dzac_type` CHECK ((zone_detail = \'DANGEROUS_GOODS\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dg_zone_allowed_classes');
    }
};
