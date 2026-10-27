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
        Schema::create('cargo_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->enum('category', ['GENERAL_CARGO', 'BREAK_BULK', 'DRY_BULK', 'LIQUID_BULK', 'GAS', 'RO_RO', 'CONTAINERIZED']);
            $table->string('imdg_class', 3)->nullable()->comment('NULL = not dangerous');
            $table->decimal('min_temperature', 5, 2)->nullable()->comment('°C; NULL = no temperature requirement');
            $table->decimal('max_temperature', 5, 2)->nullable()->comment('°C; NULL = no temperature requirement');
            $table->index(['imdg_class'], 'fk_ct_imdg');

            $table->foreign('imdg_class', 'fk_ct_imdg')->references('code')->on('imdg_classes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargo_types');
    }
};
