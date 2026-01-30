<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_company_notices', function (Blueprint $table) {

            // ✅ Aviso fijado manualmente
            $table->boolean('is_pinned')
                ->default(false)
                ->after('level');

            // ✅ Si tiene fecha → se desfija solo
            $table->timestamp('pinned_until')
                ->nullable()
                ->after('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('org_company_notices', function (Blueprint $table) {
            $table->dropColumn(['is_pinned', 'pinned_until']);
        });
    }
};
