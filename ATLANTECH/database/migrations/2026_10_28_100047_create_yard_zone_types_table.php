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
        Schema::create('yard_zone_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique('code');
            $table->string('name', 80);
            $table->enum('zone_detail', ['NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG'])->default('NONE')->comment('Which detail table zones of this type use; chosen by the admin when adding a type');
            $table->unique(['id', 'zone_detail'], 'uq_yzt_id_detail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yard_zone_types');
    }
};
