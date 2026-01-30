<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NoticeLevel;
use Illuminate\Support\Str;
class NoticeLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'name' => 'Informativo',
                'slug' => 'info',
                'color' => '#2563EB',
            ],
            [
                'name' => 'Advertencia',
                'slug' => 'warning',
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Urgente',
                'slug' => 'urgent',
                'color' => '#EF4444',
            ],
            [
                'name' => 'Éxito',
                'slug' => 'success',
                'color' => '#22C55E',
            ],
        ];

        foreach ($levels as $level) {
            NoticeLevel::create($level);
        }
    }
}
