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
     Schema::table('org_project_tasks', function (Blueprint $table) {
    $table->string('phase')->nullable()->after('task_type');
    $table->boolean('is_milestone')->default(false)->after('phase');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_project_tasks', function (Blueprint $table) {
            //
        });
    }
};
