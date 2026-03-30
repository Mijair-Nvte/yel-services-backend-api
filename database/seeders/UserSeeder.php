<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'mijairnvte@gmail.com'],
            [
                'name' => 'Mijair Navarrete',
                'password' => Hash::make('12345678'), // 🔥 cámbiala si quieres
            ]
        );
    }
}
