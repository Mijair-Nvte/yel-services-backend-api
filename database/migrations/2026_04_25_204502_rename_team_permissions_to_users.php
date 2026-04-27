<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiamos caché antes
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Renombrar view_team -> view_users
        $viewPermission = Permission::where('name', 'view_team')->first();
        if ($viewPermission) {
            $viewPermission->update(['name' => 'view_users']);
        }

        // Renombrar manage_team -> manage_users
        $managePermission = Permission::where('name', 'manage_team')->first();
        if ($managePermission) {
            $managePermission->update(['name' => 'manage_users']);
        }

        // Limpiamos caché después
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $viewPermission = Permission::where('name', 'view_users')->first();
        if ($viewPermission) {
            $viewPermission->update(['name' => 'view_team']);
        }

        $managePermission = Permission::where('name', 'manage_users')->first();
        if ($managePermission) {
            $managePermission->update(['name' => 'manage_team']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
