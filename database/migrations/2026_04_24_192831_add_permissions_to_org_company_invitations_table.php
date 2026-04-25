<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_company_invitations', function (Blueprint $table) {
            // Guardamos los permisos como un JSON array
            $table->json('permissions')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('org_company_invitations', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};