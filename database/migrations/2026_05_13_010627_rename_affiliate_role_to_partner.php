<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cambiamos el nombre en la base de datos
        DB::table('roles')
            ->where('name', 'affiliate')
            ->update(['name' => 'partner', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Revertir en caso de rollback
        DB::table('roles')
            ->where('name', 'partner')
            ->update(['name' => 'affiliate', 'updated_at' => now()]);
    }
};
