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
        Schema::create('capability_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique('code');
            $table->string('name', 100);
            $table->enum('unit', ['TONNES', 'METRES', 'TONNES_PER_HOUR', 'CUBIC_METRES_PER_HOUR', 'MOVES_PER_HOUR', 'COUNT', 'BAR']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capability_types');
    }
};
