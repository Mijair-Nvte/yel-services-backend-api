<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::table('org_customers', function (Blueprint $table) {
            // 1. Creamos la columna nullable para permitir compras sin autenticación
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('users')
                  ->nullOnDelete(); 
                  // nullOnDelete: Si eliminas al User, el Customer se mantiene 
                  // intacto como invitado para no perder el historial de compras.
        });

  
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Es importante eliminar primero la llave foránea y luego la columna
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};