<?php

namespace Database\Seeders;

use App\Models\OrgInvestorTier;
use Illuminate\Database\Seeder;

class OrgInvestorTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'name' => 'Start',
                'min_properties' => 1,
                'max_properties' => 1,
                'discount_percentage' => 0.00,
                'color_theme' => 'emerald',
                'features' => [
                    'Acceso sesión Investor Ready' => true,
                    'WhatsApp directo con Kenneth' => true,
                    'Portal personal + documentos' => true,
                    'Biblioteca de valor' => true,
                    'Acceso a videos exclusivos' => true,
                    'Mentoría 1 a 1' => false,
                    'Revisión portafolio anual' => false,
                ]
            ],
            [
                'name' => 'Plus',
                'min_properties' => 2,
                'max_properties' => 4,
                'discount_percentage' => 10.00,
                'color_theme' => 'indigo',
                'features' => [
                    'Acceso sesión Investor Ready' => true,
                    'WhatsApp directo con Kenneth' => true,
                    'Portal personal + documentos' => true,
                    'Biblioteca de valor' => true,
                    'Acceso a videos exclusivos' => true,
                    'Appraisal' => true,
                    'Prioridad en préstamo' => true,
                    'Mentoría 1 a 1' => true,
                    'Revisión portafolio anual' => false,
                ]
            ],
            [
                'name' => 'Elite',
                'min_properties' => 5,
                'max_properties' => null, // 5+
                'discount_percentage' => 20.00,
                'color_theme' => 'amber',
                'features' => [
                    'Acceso sesión Investor Ready' => true,
                    'WhatsApp directo con Kenneth' => true,
                    'Portal personal + documentos' => true,
                    'Mentoría 1 a 1' => true,
                    'Asesor dedicado' => true,
                    'Revisión portafolio anual' => true,
                    'Logo incluido en LLC' => true,
                ]
            ],
        ];

        foreach ($tiers as $tier) {
            OrgInvestorTier::updateOrCreate(
                ['name' => $tier['name'], 'org_company_id' => 1], // Ajustar ID de empresa según corresponda
                $tier
            );
        }
    }
}