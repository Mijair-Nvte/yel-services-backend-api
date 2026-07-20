<?php

namespace Database\Seeders;

use App\Models\OrgPartnerTier;
use Illuminate\Database\Seeder;

class OrgPartnerTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            [
                'org_company_id' => 1, // Tu ID de empresa principal (YEL)
                'name' => 'Nivel 1',
                'min_sales_volume' => 00.00,
                'max_sales_volume' => 5000.00,
                'commission_percentage' => 8.00,
                'features' => json_encode([
                    'Acceso al portal de ventas' => true,
                    'Soporte estándar' => true,
                    'Asignación prioritaria de leads' => false,
                ]),
                'color_theme' => 'emerald',
                'is_active' => true,
            ],
            [
                'org_company_id' => 1,
                'name' => 'Nivel 2',
                'min_sales_volume' => 5001.00,
                'max_sales_volume' => 15000.00,
                'commission_percentage' => 10.00,
                'features' => json_encode([
                    'Acceso al portal de ventas' => true,
                    'Soporte prioritario' => true,
                    'Asignación prioritaria de leads' => true,
                ]),
                'color_theme' => 'indigo',
                'is_active' => true,
            ],
            [
                'org_company_id' => 1,
                'name' => 'Nivel 3',
                'min_sales_volume' => 15001.00,
                'max_sales_volume' => null, // NULL representa que no hay límite superior
                'commission_percentage' => 12.00,
                'features' => json_encode([
                    'Acceso al portal de ventas' => true,
                    'Soporte VIP directo' => true,
                    'Asignación prioritaria de leads' => true,
                    'Bono trimestral de rendimiento' => true,
                ]),
                'color_theme' => 'amber',
                'is_active' => true,
            ],
        ];

        foreach ($tiers as $tier) {
            OrgPartnerTier::create($tier);
        }
    }
}
