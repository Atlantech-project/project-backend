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
        Schema::create('imdg_classes', function (Blueprint $table) {
            $table->string('code', 3)->primary()->comment('IMDG class / division, e.g. 3, 2.1');
            $table->string('name', 80);

            $table->comment('Fixed IMDG list; one source for every imdg_class column.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imdg_classes');
    }
};
