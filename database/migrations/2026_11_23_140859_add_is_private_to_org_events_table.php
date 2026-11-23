<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_events', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('is_active');
            $table->index(['org_company_id', 'is_private', 'starts_at'], 'org_events_company_private_starts_index');
        });
    }

    public function down(): void
    {
        Schema::table('org_events', function (Blueprint $table) {
            $table->dropIndex('org_events_company_private_starts_index');
            $table->dropColumn('is_private');
        });
    }
};