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
        Schema::create('oog_zone_details', function (Blueprint $table) {
            $table->unsignedBigInteger('yard_zone_id')->primary();
            $table->enum('zone_detail', ['NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG'])->default('OOG');
            $table->decimal('max_length', 6, 2)->nullable()->comment('m');
            $table->decimal('max_width', 6, 2)->nullable()->comment('m');
            $table->decimal('max_height', 6, 2)->nullable()->comment('m');
            $table->decimal('max_weight', 8, 2)->nullable()->comment('tonnes');
            $table->index(['yard_zone_id', 'zone_detail'], 'fk_ozd_zone');

            $table->foreign(['yard_zone_id', 'zone_detail'], 'fk_ozd_zone')->references(['id', 'zone_detail'])->on('yard_zones');
        });

        DB::statement('ALTER TABLE `oog_zone_details` ADD CONSTRAINT `chk_ozd_positive` CHECK ((((max_length is null) or (max_length > 0)) and ((max_width is null) or (max_width > 0)) and ((max_height is null) or (max_height > 0)) and ((max_weight is null) or (max_weight > 0))))');
        DB::statement('ALTER TABLE `oog_zone_details` ADD CONSTRAINT `chk_ozd_type` CHECK ((zone_detail = \'OOG\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oog_zone_details');
    }
};
