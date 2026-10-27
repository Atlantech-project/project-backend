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
        Schema::create('container_size_types', function (Blueprint $table) {
            $table->id();
            $table->char('code', 4)->unique('code')->comment('ISO 6346 size-type code, e.g. 22G1, 45R1');
            $table->integer('length_ft');
            $table->boolean('is_high_cube')->default(false)->comment('9\'6" high');
            $table->enum('type_group', ['GENERAL', 'REEFER', 'TANK', 'OPEN_TOP', 'FLAT_RACK', 'VENTILATED', 'BULK']);
            $table->decimal('teu_factor', 3, 2);
            $table->boolean('is_reefer')->nullable()->storedAs('`type_group` = \'REEFER\'')->comment('GENERATED (stored): (type_group = _latin1\'REEFER\')');
        });

        DB::statement('ALTER TABLE `container_size_types` ADD CONSTRAINT `chk_cst_length` CHECK ((length_ft in (10,20,40,45)))');
        DB::statement('ALTER TABLE `container_size_types` ADD CONSTRAINT `chk_cst_teu` CHECK ((teu_factor > 0))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_size_types');
    }
};
