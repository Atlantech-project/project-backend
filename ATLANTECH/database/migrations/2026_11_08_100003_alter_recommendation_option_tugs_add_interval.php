<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * DEC-32 open item 2 / AI data gap G-14: the towage job interval of one tug in an option, written by the AI. NULL = not
 * known; the option window applies (DEC-32 rule 4). Additive only (CT-17 minor version, ai-impact). `w_part2` already
 * has INSERT on the table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recommendation_option_tugs', function (Blueprint $table) {
            $table->dateTime('planned_start')->nullable()->after('tugboat_id')->comment('UTC. Towage job start of this tug; NULL = use the option planned_start (DEC-32 rule 4)');
            $table->dateTime('planned_end')->nullable()->after('planned_start')->comment('UTC. Towage job end of this tug; NULL = use the option planned_end (DEC-32 rule 4)');
        });

        DB::statement('ALTER TABLE `recommendation_option_tugs` ADD CONSTRAINT `chk_rot_interval` CHECK (((`planned_start` is null) = (`planned_end` is null)) and ((`planned_start` is null) or (`planned_end` > `planned_start`)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `recommendation_option_tugs` DROP CHECK `chk_rot_interval`');

        Schema::table('recommendation_option_tugs', function (Blueprint $table) {
            $table->dropColumn(['planned_start', 'planned_end']);
        });
    }
};
