<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_events', function (Blueprint $table) {
            $table->string('external_url')->nullable()->after('meeting_url');
        });
    }

    public function down(): void
    {
        Schema::table('org_events', function (Blueprint $table) {
            $table->dropColumn('external_url');
        });
    }
};
