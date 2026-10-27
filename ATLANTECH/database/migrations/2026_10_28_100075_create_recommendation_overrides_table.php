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
        Schema::create('recommendation_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recommendation_option_id');
            $table->enum('component_type', ['BERTH', 'PILOT', 'TUG', 'START_TIME']);
            $table->unsignedBigInteger('original_berth_id')->nullable();
            $table->unsignedBigInteger('override_berth_id')->nullable();
            $table->unsignedBigInteger('original_pilot_user_id')->nullable();
            $table->unsignedBigInteger('override_pilot_user_id')->nullable();
            $table->unsignedBigInteger('original_tugboat_id')->nullable()->comment('NULL = tug added');
            $table->unsignedBigInteger('override_tugboat_id')->nullable()->comment('NULL = tug removed');
            $table->dateTime('original_start')->nullable();
            $table->dateTime('override_start')->nullable()->comment('New end is computed: override_start + option service_time_p50_hours (P16-03)');
            $table->string('override_reason', 500);
            $table->unsignedBigInteger('overridden_by');
            $table->dateTime('overridden_at')->useCurrent();
            $table->index(['override_berth_id'], 'fk_rov_new_berth');
            $table->index(['override_pilot_user_id'], 'fk_rov_new_pilot');
            $table->index(['override_tugboat_id'], 'fk_rov_new_tug');
            $table->index(['recommendation_option_id'], 'fk_rov_option');
            $table->index(['original_berth_id'], 'fk_rov_orig_berth');
            $table->index(['original_pilot_user_id'], 'fk_rov_orig_pilot');
            $table->index(['original_tugboat_id'], 'fk_rov_orig_tug');
            $table->index(['overridden_by'], 'fk_rov_user');

            $table->foreign('override_berth_id', 'fk_rov_new_berth')->references('id')->on('berths');
            $table->foreign('override_pilot_user_id', 'fk_rov_new_pilot')->references('user_id')->on('pilot_profiles');
            $table->foreign('override_tugboat_id', 'fk_rov_new_tug')->references('id')->on('tugboats');
            $table->foreign('recommendation_option_id', 'fk_rov_option')->references('id')->on('recommendation_options');
            $table->foreign('original_berth_id', 'fk_rov_orig_berth')->references('id')->on('berths');
            $table->foreign('original_pilot_user_id', 'fk_rov_orig_pilot')->references('user_id')->on('pilot_profiles');
            $table->foreign('original_tugboat_id', 'fk_rov_orig_tug')->references('id')->on('tugboats');
            $table->foreign('overridden_by', 'fk_rov_user')->references('id')->on('users');

            $table->comment('Typed override of a recommendation option (FR-1.4.7). Signal for Part 3, not an automatic retrain trigger (D12).');
        });

        DB::statement('ALTER TABLE `recommendation_overrides` ADD CONSTRAINT `chk_rov_berth` CHECK ((((component_type = \'BERTH\') = (override_berth_id is not null)) and ((component_type = \'BERTH\') = (original_berth_id is not null))))');
        DB::statement('ALTER TABLE `recommendation_overrides` ADD CONSTRAINT `chk_rov_pilot` CHECK (((component_type = \'PILOT\') = ((original_pilot_user_id is not null) or (override_pilot_user_id is not null))))');
        DB::statement('ALTER TABLE `recommendation_overrides` ADD CONSTRAINT `chk_rov_time` CHECK ((((component_type = \'START_TIME\') = (override_start is not null)) and ((component_type = \'START_TIME\') = (original_start is not null))))');
        DB::statement('ALTER TABLE `recommendation_overrides` ADD CONSTRAINT `chk_rov_tug` CHECK (((component_type = \'TUG\') = ((original_tugboat_id is not null) or (override_tugboat_id is not null))))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_overrides');
    }
};
