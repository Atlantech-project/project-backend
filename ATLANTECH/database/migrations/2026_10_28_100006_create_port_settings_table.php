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
        Schema::create('port_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 80)->unique('setting_key')->comment('reminder_lead_minutes, weather_pull_hours, retrain_interval_days ...');
            $table->string('setting_value', 255);
            $table->enum('value_type', ['INT', 'DECIMAL', 'BOOL', 'STRING', 'JSON']);
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()->comment('Version token for the 409 check (microseconds)');
            $table->index(['updated_by'], 'fk_port_setting_updated_by');

            $table->foreign('updated_by', 'fk_port_setting_updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_settings');
    }
};
