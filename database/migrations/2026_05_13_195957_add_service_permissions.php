<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Limpiar caché de Spatie para evitar errores de permisos no encontrados
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Definir los nuevos permisos
        $permissions = [
            'view_services',
            'manage_services',
        ];

        // 3. Crear los permisos si no existen
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // 4. Buscar el rol admin y asignarle los permisos
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
        // Limpiar caché
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_services',
            'manage_services',
        ];

        // En caso de rollback, le quitamos los permisos al admin y los borramos
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        
        if ($adminRole) {
            $adminRole->revokePermissionTo($permissions);
        }

        foreach ($permissions as $permission) {
            $perm = Permission::where('name', $permission)->where('guard_name', 'web')->first();
            if ($perm) {
                $perm->delete();
            }
        }
    }
};