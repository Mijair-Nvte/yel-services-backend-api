<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Limpiamos la caché de Spatie antes de inyectar nuevos permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Crear los permisos
        $permissions = [
            'view_loan',
            'manage_loan',
        ];

        foreach ($permissions as $permissionName) {
            // Usamos firstOrCreate por si ejecutas la migración múltiples veces
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // 2. Asignar los permisos al rol de 'admin' (Si existe)
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_loan',
            'manage_loan',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->where('guard_name', 'web')->first();
            if ($permission) {
                $permission->delete();
            }
        }
    }
};