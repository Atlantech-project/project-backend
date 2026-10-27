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
        Schema::create('container_manifest_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('baplie_file_id');
            $table->unsignedBigInteger('container_id');
            $table->enum('full_empty', ['FULL', 'EMPTY'])->nullable()->comment('NULL = missing in BAPLIE; staff fixes it (FR-1.2.5). Backend blocks CUSTOMS_APPROVED and yard recommendations while any line is incomplete');
            $table->integer('weight_kg')->nullable()->comment('Gross weight (VGM), kg');
            $table->string('imdg_class', 3)->nullable()->comment('NULL = not dangerous');
            $table->integer('oog_over_front_cm')->nullable()->comment('BAPLIE DIM overhang, cm; NULL = none. Overall size = standard size (container_size_type) + overhangs, compared with oog_zone_detail');
            $table->integer('oog_over_back_cm')->nullable();
            $table->integer('oog_over_right_cm')->nullable();
            $table->integer('oog_over_left_cm')->nullable();
            $table->integer('oog_over_height_cm')->nullable();
            $table->decimal('reefer_set_temperature', 5, 2)->nullable()->comment('°C. Reefer (container_size_type.is_reefer) with NULL here = data error flagged by the parser');
            $table->integer('stowage_bay')->nullable();
            $table->integer('stowage_row')->nullable()->comment('00 = centre line');
            $table->integer('stowage_tier')->nullable();
            $table->char('port_of_load', 5)->nullable()->comment('UN/LOCODE');
            $table->char('port_of_discharge', 5)->nullable()->comment('UN/LOCODE; = port_profile.port_code means discharged here');
            $table->index(['container_id'], 'fk_cme_container');
            $table->index(['imdg_class'], 'fk_cme_imdg');
            $table->unique(['baplie_file_id', 'container_id'], 'uq_cme_file_container');

            $table->foreign('container_id', 'fk_cme_container')->references('id')->on('containers');
            $table->foreign('baplie_file_id', 'fk_cme_file')->references('id')->on('baplie_files');
            $table->foreign('imdg_class', 'fk_cme_imdg')->references('code')->on('imdg_classes');

            $table->comment('Per-call container data from BAPLIE. Container identity in container (Module 5).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_manifest_entries');
    }
};
