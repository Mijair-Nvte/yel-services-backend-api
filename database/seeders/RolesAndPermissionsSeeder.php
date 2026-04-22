<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Resetear la caché de roles y permisos (Buena práctica al correr seeders)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Definir los "Switches" basados en las entidades de tu Base de Datos
        $permissions = [
            // 📊 Dashboard general
            'view_dashboard',

            // 👥 Equipo, Áreas y Posiciones (org_company_users, org_areas, org_positions)
            'view_team',
            'manage_team',
            'view_areas',
            'manage_areas',

            // 📅 Eventos y Calendario (org_events)
            'view_calendar',
            'manage_calendar',

            // 💰 Ventas y Mapeos de Links (org_sales, org_payment_link_mappings)
            'view_sales',
            'manage_sales',
            'view_payment_links',
            'manage_payment_links',

            // 📢 Avisos (org_company_notices)
            'view_notices',
            'manage_notices',

            // 🔗 Enlaces de la Compañía (org_company_links)
            'view_company_links',
            'manage_company_links',

            // 📂 Gestor de Archivos (folders, documents)
            'view_documents',
            'manage_documents',

            // 💬 Chat (chat_conversations, chat_messages)
            // Normalmente todos tienen acceso, pero puedes apagarlo si alguien está suspendido
            'access_chat',
        ];

        // 3. Insertar los permisos en la base de datos
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web', // o 'api' si configuraste Sanctum para usar api por defecto
            ]);
        }

        // 4. Crear los dos únicos roles: 'admin' y 'user'
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);

        // 5. Asignar TODOS los permisos al Rol de Administrador
        // El admin tiene todos los "switches" encendidos por defecto
        $adminRole->syncPermissions(Permission::all());

        // 6. Asignar los permisos BASE al Rol de Usuario (Lo que pueden ver por defecto)
        // Puedes ajustar esto según tu lógica de negocio
        $userRole->syncPermissions([
            'view_dashboard',
            'view_calendar',
            'view_notices',
            'view_company_links',
            'view_documents',
            'access_chat',
        ]);
    }
}
