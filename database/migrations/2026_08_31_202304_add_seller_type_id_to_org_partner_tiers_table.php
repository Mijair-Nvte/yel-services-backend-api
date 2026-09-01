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
        Schema::table('org_partner_tiers', function (Blueprint $table) {
            // Si la columna ya existe, la omite; si no, la crea con su llave foránea
            if (!Schema::hasColumn('org_partner_tiers', 'org_seller_type_id')) {
                $table->foreignId('org_seller_type_id')
                    ->nullable()
                    ->after('org_company_id')
                    ->constrained('org_seller_types')
                    ->nullOnDelete();
            }
        });

        // Asignar el tipo 1 (Externo) solo a los registros que aún no lo tengan asignado 
        // y validando que el ID 1 exista para evitar violaciones de llave foránea.
        if (DB::table('org_seller_types')->where('id', 1)->exists()) {
            DB::table('org_partner_tiers')
                ->whereNull('org_seller_type_id')
                ->update(['org_seller_type_id' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_partner_tiers', function (Blueprint $table) {
            if (Schema::hasColumn('org_partner_tiers', 'org_seller_type_id')) {
                $table->dropForeign(['org_seller_type_id']);
                $table->dropColumn('org_seller_type_id');
            }
        });
    }
};