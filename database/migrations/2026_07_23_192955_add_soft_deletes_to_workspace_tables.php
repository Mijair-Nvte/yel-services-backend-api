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
        Schema::table('org_companies', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('users', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('user_profiles', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('documents', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('folders', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('org_areas', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('org_positions', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('org_events', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('org_event_attendees', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('org_company_notices', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('org_properties', function (Blueprint $table) { $table->softDeletes(); });
        Schema::table('org_time_trackings', function (Blueprint $table) { $table->softDeletes(); });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_companies', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('users', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('user_profiles', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('documents', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('folders', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('org_areas', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('org_positions', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('org_events', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('org_event_attendees', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('org_company_notices', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('org_properties', function (Blueprint $table) { $table->dropSoftDeletes(); });
        Schema::table('org_time_trackings', function (Blueprint $table) { $table->dropSoftDeletes(); });
    }
};