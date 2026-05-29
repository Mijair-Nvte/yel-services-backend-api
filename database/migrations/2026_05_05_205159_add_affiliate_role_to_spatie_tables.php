<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Creamos el rol de afiliado de forma segura
        // Usamos firstOrCreate para evitar errores si ya existiera por alguna razón
        Role::firstOrCreate([
            'name' => 'affiliate',
            'guard_name' => 'web'
        ]);
    }

    public function down(): void
    {
        // En caso de rollback, eliminamos el rol
        Role::where('name', 'affiliate')->where('guard_name', 'web')->delete();
    }
};