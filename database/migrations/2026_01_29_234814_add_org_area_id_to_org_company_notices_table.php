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
        Schema::table('org_company_notices', function (Blueprint $table) {

            $table->foreignId('org_area_id')
                ->nullable()
                ->after('org_company_id')
                ->constrained('org_areas')
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_company_notices', function (Blueprint $table) {
            $table->dropForeign(['org_area_id']);
            $table->dropColumn('org_area_id');
        });

    }
};
