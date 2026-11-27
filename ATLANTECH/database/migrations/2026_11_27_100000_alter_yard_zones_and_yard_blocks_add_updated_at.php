<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * P2-F4 (Q7): the version token (CT-01 §5) for yard zones and blocks, which the CT Administrator edits. Same column as on
 * `yard_slots` and `equipment`: microseconds, set by the database on insert and update. Additive only (CT-17 minor
 * version, ai-impact: workers read both tables).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['yard_zones', 'yard_blocks'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()->comment('Version token for the 409 check (microseconds)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['yard_zones', 'yard_blocks'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('updated_at');
            });
        }
    }
};
