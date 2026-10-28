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
        Schema::create('recommendation_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('recommendation_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('viewed_at')->useCurrent();
            $table->primary(['recommendation_id', 'user_id', 'viewed_at']);

            $table->foreign('recommendation_id', 'fk_rrv_rec')->references('id')->on('recommendations');
            $table->foreign('user_id', 'fk_rrv_user')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_reviews');
    }
};
