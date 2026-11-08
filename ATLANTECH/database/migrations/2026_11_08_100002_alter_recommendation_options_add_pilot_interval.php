<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * DEC-32 open item 2 / AI data gap G-14: the pilotage job interval of an option, written by the AI. NULL = not known; the
 * option window (planned_start, planned_end) applies, which over-reserves the pilot (DEC-32 rule 4).
 * Additive only (CT-17 minor version, ai-impact). `w_part2` already has INSERT on the table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recommendation_options', function (Blueprint $table) {
            $table->dateTime('pilot_start')->nullable()->after('pilot_user_id')->comment('UTC. Pilotage job start; NULL = use planned_start (DEC-32 rule 4)');
            $table->dateTime('pilot_end')->nullable()->after('pilot_start')->comment('UTC. Pilotage job end; NULL = use planned_end (DEC-32 rule 4)');
        });

        DB::statement('ALTER TABLE `recommendation_options` ADD CONSTRAINT `chk_ro_pilot_interval` CHECK (((`pilot_start` is null) = (`pilot_end` is null)) and ((`pilot_start` is null) or ((`pilot_user_id` is not null) and (`pilot_end` > `pilot_start`))))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `recommendation_options` DROP CHECK `chk_ro_pilot_interval`');

        Schema::table('recommendation_options', function (Blueprint $table) {
            $table->dropColumn(['pilot_start', 'pilot_end']);
        });
    }
};
