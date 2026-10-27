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
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->char('container_number', 11)->unique()->comment('ISO 6346, uppercase, no spaces; check digit validated by parser (warning)');
            $table->unsignedBigInteger('size_type_id');
            $table->index(['size_type_id'], 'idx_container_size_type');

            $table->foreign('size_type_id', 'fk_container_size_type')->references('id')->on('container_size_types');

            $table->comment('Identity only. Per-call attributes (weight, stowage, ports) live in container_manifest_entry.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
