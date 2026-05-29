<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Creamos los permisos
        $permissions = [
            'view_investors',
            'manage_investors'
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Opcional: Asignar directamente al rol 'admin' globalmente
        // Si tu lógica de roles es diferente, puedes quitar este bloque
        $adminRole = Role::where('name', 'admin')->whereNull('org_company_id')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        // Elimina los permisos si hacemos rollback
        Permission::whereIn('name', ['view_investors', 'manage_investors'])->delete();
    }
};