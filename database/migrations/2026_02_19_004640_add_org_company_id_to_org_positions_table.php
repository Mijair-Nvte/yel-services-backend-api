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
        Schema::table('org_positions', function (Blueprint $table) {
            $table->foreignId('org_company_id')
                ->after('id')
                ->constrained('org_companies')
                ->cascadeOnDelete();

            $table->unique(['org_company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('org_positions', function (Blueprint $table) {
            $table->dropForeign(['org_company_id']);
            $table->dropUnique(['org_company_id', 'slug']);
            $table->dropColumn('org_company_id');
        });
    }
};
