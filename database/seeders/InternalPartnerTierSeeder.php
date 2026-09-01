<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalPartnerTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            [
                'uid' => 'ptier_' . strtolower(Str::random(21)),
                'org_company_id' => 1,
                'org_seller_type_id' => 2, // Vendedor Interno
                'name' => 'Nivel 1 — Arranque',
                'min_sales_volume' => 0.00,
                'max_sales_volume' => 5000.00,
                'commission_percentage' => 8.00,
                'features' => json_encode(['Soporte básico', 'Acceso a plataforma']),
                'color_theme' => 'blue',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uid' => 'ptier_' . strtolower(Str::random(21)),
                'org_company_id' => 1,
                'org_seller_type_id' => 2, // Vendedor Interno
                'name' => 'Nivel 2 — Crecimiento',
                'min_sales_volume' => 5001.00,
                'max_sales_volume' => 15000.00,
                'commission_percentage' => 10.00,
                'features' => json_encode(['Soporte prioritario', 'Asignación de leads']),
                'color_theme' => 'purple',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uid' => 'ptier_' . strtolower(Str::random(21)),
                'org_company_id' => 1,
                'org_seller_type_id' => 2, // Vendedor Interno
                'name' => 'Nivel 3 — Élite',
                'min_sales_volume' => 15001.00,
                'max_sales_volume' => 99999999.99, // Representa "o más"
                'commission_percentage' => 12.00,
                'features' => json_encode(['Soporte 24/7', 'Leads premium', 'Bonos extra']),
                'color_theme' => 'gold',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('org_partner_tiers')->insert($tiers);
    }
}