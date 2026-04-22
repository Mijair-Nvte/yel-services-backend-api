<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $teamKey = config('permission.column_names.team_foreign_key', 'org_company_id');

        // 1. Agregamos la columna a 'roles'
        Schema::table('roles', function (Blueprint $table) use ($teamKey) {
            if (!Schema::hasColumn('roles', $teamKey)) {
                $table->unsignedBigInteger($teamKey)->nullable()->after('id');
            }
        });

        // 2. Agregamos a 'model_has_roles' y actualizamos las llaves primarias
        Schema::table('model_has_roles', function (Blueprint $table) use ($teamKey) {
            if (!Schema::hasColumn('model_has_roles', $teamKey)) {
                $table->unsignedBigInteger($teamKey)->default(1); 
                
                $table->dropPrimary();
                $table->primary(
                    [$teamKey, 'role_id', 'model_id', 'model_type'], 
                    'model_has_roles_team_primary'
                );
            }
        });

        // 3. Agregamos a 'model_has_permissions' y actualizamos llaves primarias
        Schema::table('model_has_permissions', function (Blueprint $table) use ($teamKey) {
            if (!Schema::hasColumn('model_has_permissions', $teamKey)) {
                $table->unsignedBigInteger($teamKey)->default(1);
                
                $table->dropPrimary();
                $table->primary(
                    [$teamKey, 'permission_id', 'model_id', 'model_type'], 
                    'model_has_permissions_team_primary'
                );
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