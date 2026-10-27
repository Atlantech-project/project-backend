<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('terminal_id');
            $table->unsignedBigInteger('equipment_category_id');
            $table->integer('quantity')->comment('How many units of this type the terminal has');
            $table->integer('maintenance_quantity')->default(0);
            $table->integer('blocked_quantity')->default(0);
            $table->integer('available_quantity')->nullable()->storedAs('`quantity` - `maintenance_quantity` - `blocked_quantity`')->comment('GENERATED (stored): ((quantity - maintenance_quantity) - blocked_quantity) | Usable units; units busy in a plan are derived from plans (Module 18)');
            $table->index(['equipment_category_id'], 'idx_eq_category');
            $table->unique(['id', 'terminal_id'], 'uq_eq_id_terminal');
            $table->unique(['terminal_id', 'equipment_category_id'], 'uq_eq_terminal_category');

            $table->foreign('equipment_category_id', 'fk_eq_category')->references('id')->on('equipment_categories');
            $table->foreign('terminal_id', 'fk_eq_terminal')->references('id')->on('terminals');
        });

        DB::statement('ALTER TABLE `equipment` ADD CONSTRAINT `chk_eq_quantity` CHECK (((quantity > 0) and (maintenance_quantity >= 0) and (blocked_quantity >= 0) and ((maintenance_quantity + blocked_quantity) <= quantity)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
