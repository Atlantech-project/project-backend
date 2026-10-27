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
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->char('imo_number', 7)->unique()->comment('7 digits, no "IMO" prefix; backend validates the check digit');
            $table->string('name', 120);
            $table->unsignedBigInteger('vessel_type_id');
            $table->decimal('loa', 7, 2)->comment('Length overall, m');
            $table->decimal('beam', 6, 2)->comment('Breadth, m. Needed for squat (blockage)');
            $table->decimal('max_draft', 5, 2)->comment('Summer (max) draft, m; arrival_draft must not exceed it');
            $table->integer('net_tonnage')->nullable()->comment('NT. Nullable: not in BAPLIE; backend requires it at Preliminary Approval');
            $table->decimal('moulded_depth', 5, 2)->nullable()->comment('m. Nullable, same rule');
            $table->string('call_sign', 10)->nullable()->comment('Nullable');
            $table->char('flag_state', 2)->nullable()->comment('ISO 3166-1 alpha-2. Nullable');

            $table->foreign('vessel_type_id', 'fk_vessel_type')->references('id')->on('vessel_types');

            $table->comment('Block coefficient (Cb), GT and DWT are per call: they live on berthing_request.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessels');
    }
};
