<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $teamKey = config('permission.column_names.team_foreign_key', 'org_company_id');

        // 1. Agregamos a 'roles'
        Schema::table('roles', function (Blueprint $table) use ($teamKey) {
            if (!Schema::hasColumn('roles', $teamKey)) {
                $table->unsignedBigInteger($teamKey)->nullable()->after('id');
            }
        });

        // 2. Agregamos a 'model_has_roles'
        Schema::table('model_has_roles', function (Blueprint $table) use ($teamKey) {
            if (!Schema::hasColumn('model_has_roles', $teamKey)) {
                $table->unsignedBigInteger($teamKey)->default(1); 
            }
        });

        // 3. Agregamos a 'model_has_permissions'
        Schema::table('model_has_permissions', function (Blueprint $table) use ($teamKey) {
            if (!Schema::hasColumn('model_has_permissions', $teamKey)) {
                $table->unsignedBigInteger($teamKey)->default(1);
            }
        });
    }

    public function down()
    {
        $teamKey = config('permission.column_names.team_foreign_key', 'org_company_id');

        Schema::table('roles', function (Blueprint $table) use ($teamKey) {
            $table->dropColumn($teamKey);
        });
        Schema::table('model_has_roles', function (Blueprint $table) use ($teamKey) {
            $table->dropColumn($teamKey);
        });
        Schema::table('model_has_permissions', function (Blueprint $table) use ($teamKey) {
            $table->dropColumn($teamKey);
        });
    }
};