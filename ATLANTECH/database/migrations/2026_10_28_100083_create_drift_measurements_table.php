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
        Schema::create('drift_measurements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('model_version_id');
            $table->enum('metric_type', ['PSI', 'ROLLING_MAE']);
            $table->string('feature_name', 60)->nullable()->comment('Required for PSI');
            $table->decimal('metric_value', 14, 5);
            $table->decimal('threshold', 14, 5);
            $table->boolean('is_drifted')->nullable()->storedAs('`metric_value` > `threshold`')->comment('GENERATED (stored): (metric_value > threshold)');
            $table->dateTime('measured_at');
            $table->dateTime('notified_at')->nullable()->comment('Set by the Backend when the drift alert is sent (Backend only)');
            $table->index(['model_version_id'], 'fk_dm_version');

            $table->foreign('model_version_id', 'fk_dm_version')->references('id')->on('ml_model_versions');
        });

        DB::statement('ALTER TABLE `drift_measurements` ADD CONSTRAINT `chk_dm_feature` CHECK (((metric_type = \'PSI\') = (feature_name is not null)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drift_measurements');
    }
};
