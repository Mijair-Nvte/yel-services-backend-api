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
        Schema::table('org_area_user_roles', function (Blueprint $table) {
            $table->string('position_title')
                ->nullable()
                ->after('org_role_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_area_user_roles', function (Blueprint $table) {
            //
        });
    }
};
