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
        Schema::create('rationale_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60);
            $table->integer('version');
            $table->text('template_text');
            $table->boolean('is_active')->default(true);
            $table->string('active_code', 60)->nullable()->unique()->comment('GENERATED (stored): if(is_active,code,NULL)');
            $table->unique(['code', 'version'], 'uq_rt_code_version');

            $table->comment('Fixed, versioned templates; never LLM (D11).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rationale_templates');
    }
};
