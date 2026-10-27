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
        Schema::create('tugboats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->decimal('tug_power', 8, 2);
            $table->enum('tug_power_unit', ['BOLLARD_PULL_TONNES', 'HP', 'KW'])->comment('Chosen by the Tug Manager');
            $table->decimal('capacity', 8, 2)->nullable()->comment('Kept as in source (P4-03 rejected)');
            $table->enum('status', ['AVAILABLE', 'BUSY', 'MAINTENANCE', 'INACTIVE'])->default('AVAILABLE')->comment('BUSY for the plan duration; Tug Master sets it back to AVAILABLE from the tablet; Tug Officer notified if still BUSY after planned_end');

            $table->comment('Port tug fleet (shared Marine Services resource).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tugboats');
    }
};
