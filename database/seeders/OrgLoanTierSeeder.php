<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrgLoanTier;

class OrgLoanTierSeeder extends Seeder
{
    public function run(): void
    {
        $commonData = [
            'org_company_id'         => 1,
            'small_loan_max_amount'  => 149999.99,
            'large_loan_min_amount'  => 250000.00,
            'medium_loan_percentage' => 0.0020,
            'is_active'              => true,
        ];

        $tiers = [
            [
                'name'                 => 'Arranque',
                'min_monthly_volume'   => 0.00,
                'max_monthly_volume'   => 500000.00,
                'small_loan_fixed_fee' => 100.00,
                'large_loan_cap'       => 500.00,
                'color_theme'          => 'emerald',
                'features'             => [
                    'Mentoría 1 a 1' => false,
                    'Biblioteca de valor' => true,
                    'Asesor dedicado' => false,
                ],
            ],
            [
                'name'                 => 'Crecimiento',
                'min_monthly_volume'   => 500001.00,
                'max_monthly_volume'   => 1500000.00,
                'small_loan_fixed_fee' => 125.00,
                'large_loan_cap'       => 650.00,
                'color_theme'          => 'indigo',
                'features'             => [
                    'Mentoría 1 a 1' => true,
                    'Biblioteca de valor' => true,
                    'Asesor dedicado' => false,
                ],
            ],
            [
                'name'                 => 'Élite',
                'min_monthly_volume'   => 1500001.00,
                'max_monthly_volume'   => null,
                'small_loan_fixed_fee' => 150.00,
                'large_loan_cap'       => 800.00,
                'color_theme'          => 'amber',
                'features'             => [
                    'Mentoría 1 a 1' => true,
                    'Biblioteca de valor' => true,
                    'Asesor dedicado' => true,
                ],
            ]
        ];

        foreach ($tiers as $tier) {
            OrgLoanTier::updateOrCreate(
                [
                    'org_company_id' => 1,
                    'name'           => $tier['name'],
                ],
                array_merge($tier, $commonData)
            );
        }

        $this->command->info('Niveles de préstamo actualizados con features y colores.');
    }
}