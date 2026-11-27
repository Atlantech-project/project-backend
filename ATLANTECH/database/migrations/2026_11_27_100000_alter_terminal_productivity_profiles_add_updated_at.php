<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * P2-F6: the version token of a productivity profile. P1-B2 answer 4 (lead): this table gets `updated_at` when a screen
 * edits it, and P-48 does. Additive only (CT-17 minor version, ai-impact): the AI workers only read the table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('terminal_productivity_profiles', function (Blueprint $table) {
            $table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()->comment('Version token for the 409 check (microseconds)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terminal_productivity_profiles', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }
};
