<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- Importante agregar esto

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('org_partner_tiers', function (Blueprint $table) {
            $table->foreignId('org_seller_type_id')
                ->nullable()
                ->after('org_company_id')
                ->constrained('org_seller_types')
                ->nullOnDelete();
        });

        // Asignar el tipo 1 (Externo) a todos los tiers existentes automáticamente
        DB::table('org_partner_tiers')->update(['org_seller_type_id' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_partner_tiers', function (Blueprint $table) {
            // Es importante eliminar primero la llave foránea y luego la columna
            $table->dropForeign(['org_seller_type_id']);
            $table->dropColumn('org_seller_type_id');
        });
    }
};