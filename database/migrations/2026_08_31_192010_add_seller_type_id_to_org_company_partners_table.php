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
        // 1. Agregar la columna a la tabla de partners
        Schema::table('org_company_partners', function (Blueprint $table) {
            // Se agrega nullable primero para evitar errores con registros existentes
            $table->unsignedBigInteger('org_seller_type_id')->nullable()->after('org_company_id');

            $table->foreign('org_seller_type_id')
                  ->references('id')
                  ->on('org_seller_types')
                  ->onDelete('set null'); // Si se borra el tipo, el partner no se borra, solo queda null
        });

        // 2. Asignar a todos los partners existentes como "Externos" por defecto
        $externalType = DB::table('org_seller_types')->where('slug', 'external')->first();

        if ($externalType) {
            DB::table('org_company_partners')->update([
                'org_seller_type_id' => $externalType->id
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_company_partners', function (Blueprint $table) {
            $table->dropForeign(['org_seller_type_id']);
            $table->dropColumn('org_seller_type_id');
        });
    }
};