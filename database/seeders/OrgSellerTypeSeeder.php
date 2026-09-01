<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrgSellerType;
use App\Models\OrgCompany; // Ajusta este namespace al modelo correcto de tus compañías

class OrgSellerTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Obtenemos todas las compañías actuales del sistema
        $companies = OrgCompany::all();

        foreach ($companies as $company) {
            
            // Usamos firstOrCreate para que si lo corres 2 veces, no se duplique (Idempotencia)
            
            // 1. Vendedor Externo (Afiliados en YEL Investor / YEL PRO)
            OrgSellerType::firstOrCreate([
                'org_company_id' => $company->id,
                'slug' => 'external'
            ], [
                'name' => 'Externo',
                'description' => 'Usuarios afiliados y externos a la empresa',
                'is_active' => true,
            ]);

            // 2. Vendedor Interno (Equipo de YEL Services)
            OrgSellerType::firstOrCreate([
                'org_company_id' => $company->id,
                'slug' => 'internal'
            ], [
                'name' => 'Interno',
                'description' => 'Miembros del equipo de la empresa con permisos de venta',
                'is_active' => true,
            ]);
        }
    }
}