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
        Schema::create('storage_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->enum('detail_kind', ['NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK'])->default('NONE')->comment('Which detail table facilities of this type may use');
            $table->unique(['id', 'detail_kind'], 'uq_storage_type_id_kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_types');
    }
};
