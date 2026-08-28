<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Limpiamos la caché de Spatie para evitar errores
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Crear los nuevos permisos
        $permissions = [
            'view_company_settings',
            'manage_company_settings',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // 2. Obtener el rol 'admin' (basado en tu DB, es un rol global así que no pasamos org_company_id)
        // Opcional: Asignar directamente al rol 'admin' globalmente
        // Si tu lógica de roles es diferente, puedes quitar este bloque
        $adminRole = Role::where('name', 'admin')->whereNull('org_company_id')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Limpiamos la caché
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_company_settings',
            'manage_company_settings',
        ];

        // Eliminar los permisos (Spatie automáticamente limpia la tabla pivot role_has_permissions)
        Permission::whereIn('name', $permissions)
            ->where('guard_name', 'web')
            ->delete();
    }
};
