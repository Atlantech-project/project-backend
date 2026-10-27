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
        Schema::create('constraint_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->enum('category', ['ENVIRONMENTAL', 'UKC', 'VESSEL_SAFETY']);
            $table->enum('unit', ['KNOTS', 'METRES_PER_SECOND', 'METRES', 'NAUTICAL_MILES', 'PERCENT', 'DEGREES', 'MINUTES', 'TONNES']);
            $table->enum('comparator', ['MAX_ALLOWED', 'MIN_REQUIRED']);
            $table->boolean('is_hard_safety_limit')->default(true)->comment('TRUE = never modified by Part 3 / retraining (D12). Not the solver hard/soft choice (D13, deferred)');
            $table->string('description', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('constraint_types');
    }
};
