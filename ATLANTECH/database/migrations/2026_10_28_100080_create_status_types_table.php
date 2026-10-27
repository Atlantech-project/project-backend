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
        Schema::create('status_types', function (Blueprint $table) {
            $table->enum('component_type', ['BERTH', 'PILOT', 'TUG', 'TERMINAL_OPS'])->comment('Same ENUM as plan_component.component_type');
            $table->string('code', 30);
            $table->string('name', 80);
            $table->primary(['component_type', 'code']);

            $table->comment('Allowed field statuses per component type (P19-01). New status = INSERT, not ALTER.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_types');
    }
};
