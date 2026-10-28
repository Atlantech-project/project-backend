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
        Schema::create('tide_constituent_sets', function (Blueprint $table) {
            $table->id();
            $table->string('version_label', 40)->unique('version_label');
            $table->string('datum_reference', 40)->comment('Must equal navigation_channel / berth depth_datum (backend check on activation)');
            $table->date('valid_from');
            $table->boolean('is_active')->default(false);
            $table->tinyInteger('active_key')->nullable()->storedAs('IF(`is_active`, 1, NULL)')->unique('active_key')->comment('GENERATED (stored): if(is_active,1,NULL)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tide_constituent_sets');
    }
};
