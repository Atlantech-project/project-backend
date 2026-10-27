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
        Schema::create('operational_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('berthing_request_id');
            $table->unsignedBigInteger('recommendation_id')->comment('Enforces option <-> recommendation <-> request consistency');
            $table->unsignedBigInteger('recommendation_option_id')->comment('The confirmed (selected) option');
            $table->enum('status', ['CONFIRMED', 'CANCELLED', 'COMPLETED'])->default('CONFIRMED');
            $table->unsignedBigInteger('active_key')->nullable()->storedAs('IF(`status` = \'CONFIRMED\', `berthing_request_id`, NULL)')->unique('active_key')->comment('GENERATED (stored): IF(status = CONFIRMED, berthing_request_id, NULL)');
            $table->unsignedBigInteger('confirmed_by');
            $table->dateTime('confirmed_at')->useCurrent();
            $table->index(['recommendation_option_id', 'recommendation_id'], 'fk_op_option');
            $table->index(['recommendation_id', 'berthing_request_id'], 'fk_op_rec');
            $table->index(['confirmed_by'], 'fk_op_user');

            $table->foreign(['recommendation_option_id', 'recommendation_id'], 'fk_op_option')->references(['id', 'recommendation_id'])->on('recommendation_options');
            $table->foreign(['recommendation_id', 'berthing_request_id'], 'fk_op_rec')->references(['id', 'berthing_request_id'])->on('recommendations');
            $table->foreign('confirmed_by', 'fk_op_user')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_plans');
    }
};
