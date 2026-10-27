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
        Schema::create('baplie_parse_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('baplie_file_id');
            $table->enum('severity', ['ERROR', 'WARNING']);
            $table->string('segment_ref', 60)->nullable();
            $table->string('message', 500);
            $table->index(['baplie_file_id'], 'fk_bpi_file');

            $table->foreign('baplie_file_id', 'fk_bpi_file')->references('id')->on('baplie_files');

            $table->comment('FR-1.2.4 diagnostics.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baplie_parse_issues');
    }
};
