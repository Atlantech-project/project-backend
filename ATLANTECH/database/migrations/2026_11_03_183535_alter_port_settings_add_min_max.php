<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * DEC-59 Option 1 / CT-18 v1.1: the lowest and highest value a port setting may take. NULL for STRING, JSON and BOOL keys.
 * Additive only (CT-17 minor version).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('port_settings', function (Blueprint $table) {
            $table->decimal('min_value', 18, 6)->nullable()->after('value_type')->comment('Lowest allowed value; NULL = no lower limit (CT-18)');
            $table->decimal('max_value', 18, 6)->nullable()->after('min_value')->comment('Highest allowed value; NULL = no upper limit (CT-18)');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('port_settings', function (Blueprint $table) {
            $table->dropColumn(['min_value', 'max_value']);
        });
    }
};
