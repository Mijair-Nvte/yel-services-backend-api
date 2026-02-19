<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrgCompany;
use App\Models\OrgPosition;
use Illuminate\Support\Str;

class OrgPositionSeeder extends Seeder
{
    public function run(): void
    {
        // 🔎 Obtener compañía (puedes cambiar por slug si prefieres)
        $company = OrgCompany::first(); 
        // o:
        // $company = OrgCompany::where('slug', 'tu-slug')->first();

        if (!$company) {
            $this->command->error('No existe ninguna compañía.');
            return;
        }

        $positions = [
            'Project Manager',
            'Administrador',
            'Developer',
            'Frontend Developer',
            'Backend Developer',
            'Automation Developer',
            'Community Manager',
            'Digital Traffic Manager',
            'Diseñador Gráfico',
            'Marketing Digital Specialist',
            'Copywriter',
            'SEO Specialist',
            'Paid Media Specialist',
            'Funnel Builder',
            'CRM Manager',
            'Content Creator',
            'Video Editor',
        ];

        foreach ($positions as $name) {
            OrgPosition::updateOrCreate(
                [
                    'org_company_id' => $company->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ Puestos creados correctamente.');
    }
}
