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
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique('code');
            $table->string('name', 120)->unique('name');
            $table->enum('terminal_type', ['CONTAINER', 'NON_CONTAINER']);
            $table->json('boundary_coordinates')->nullable()->comment('GeoJSON Polygon [lon, lat]; inside port boundary (app rule)');
            $table->boolean('is_active')->default(true)->comment('All registered terminals (active or not) count against license max_terminals');
            $table->unique(['id', 'terminal_type'], 'uq_terminal_id_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
