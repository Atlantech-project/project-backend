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
        Schema::create('storage_allocation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('terminal_recommendation_id');
            $table->enum('rec_type', ['TIMING', 'YARD_PLACEMENT', 'STORAGE_ALLOCATION'])->default('STORAGE_ALLOCATION');
            $table->unsignedBigInteger('storage_facility_id');
            $table->unsignedBigInteger('cargo_type_id');
            $table->decimal('quantity', 14, 2);
            $table->enum('quantity_unit', ['TONNES', 'CUBIC_METRES', 'SQUARE_METRES', 'TEU', 'UNITS'])->comment('Must equal the facility capacity_unit (composite FK)');
            $table->index(['storage_facility_id', 'cargo_type_id'], 'fk_sai_compatible');
            $table->index(['terminal_recommendation_id', 'rec_type'], 'fk_sai_tr');
            $table->index(['storage_facility_id', 'quantity_unit'], 'fk_sai_unit');
            $table->unique(['terminal_recommendation_id', 'storage_facility_id', 'cargo_type_id'], 'uq_sai_rec_facility_cargo');

            $table->foreign(['storage_facility_id', 'cargo_type_id'], 'fk_sai_compatible')->references(['storage_facility_id', 'cargo_type_id'])->on('storage_facility_cargo_compatibilities');
            $table->foreign(['terminal_recommendation_id', 'rec_type'], 'fk_sai_tr')->references(['id', 'rec_type'])->on('terminal_recommendations');
            $table->foreign(['storage_facility_id', 'quantity_unit'], 'fk_sai_unit')->references(['id', 'capacity_unit'])->on('storage_facilities');

            $table->comment('On ACCEPTED, backend adds quantity to storage_facility.reserved_capacity (D-46).');
        });

        DB::statement('ALTER TABLE `storage_allocation_items` ADD CONSTRAINT `chk_sai_quantity` CHECK ((quantity > 0))');
        DB::statement('ALTER TABLE `storage_allocation_items` ADD CONSTRAINT `chk_sai_type` CHECK ((rec_type = \'STORAGE_ALLOCATION\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_allocation_items');
    }
};
