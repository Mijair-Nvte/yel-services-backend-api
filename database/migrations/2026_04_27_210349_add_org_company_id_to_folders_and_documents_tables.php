<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->foreignId('org_company_id')
                  ->nullable()
                  ->after('uid')
                  ->constrained('org_companies')
                  ->cascadeOnDelete();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('org_company_id')
                  ->nullable()
                  ->after('uid')
                  ->constrained('org_companies')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropForeign(['org_company_id']);
            $table->dropColumn('org_company_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['org_company_id']);
            $table->dropColumn('org_company_id');
        });
    }
};