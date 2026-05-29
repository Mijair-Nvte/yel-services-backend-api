<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrgService;
use App\Models\OrgCompany;
use Illuminate\Support\Str;

class OrgServiceSeeder extends Seeder
{
    public function run(): void
    {
        // Buscamos la compañía principal (Ajusta esto si necesitas un ID en específico)
        $company = OrgCompany::first();

        if (!$company) {
            $this->command->error('No hay ninguna compañía creada. Crea una primero.');
            return;
        }

        // Lista de servicios extraída de tu imagen
        $services = [
            'AFFIDAVIT OF DEATH',
            'ASESORIA MARKETING MARCA',
            'CREA TU LLC',
            'IDENTIDAD VISUAL',
            'LEASE AGREEMENT',
            'LOGO',
            'QUICKBOOK SET UP',
            'REGISTRO DE MARCA',
            'TRÁMITE DE TRUST',
            'TRANSFER ON DEATH DEED',
            'Transforma tu esencia digital',
            'WARRANTY DEED'
        ];

        foreach ($services as $service) {
            OrgService::create([
                'uid' => 'srv_' . strtoupper(Str::random(15)),
                'org_company_id' => $company->id,
                'name' => $service,
                'description' => 'Servicio profesional de ' . strtolower($service) . '.',
                // Ponemos un ID de precio falso por ahora para preparar la integración con Stripe
                'stripe_price_id' => 'price_fake_' . strtolower(Str::random(10)), 
                'default_commission_type' => 'percentage',
                'default_commission_value' => 15.00, // 15% por defecto para todos
                'is_active' => true,
            ]);
        }

        $this->command->info('12 Servicios insertados correctamente para la compañía: ' . $company->name);
    }
}