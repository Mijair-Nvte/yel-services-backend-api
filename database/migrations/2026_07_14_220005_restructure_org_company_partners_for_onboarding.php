<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_company_partners', function (Blueprint $table) {
            // 1. Hacemos el código de referido opcional
            $table->string('referral_code')->nullable()->change();
            
            // 2. Agregamos el control del formulario fiscal
            $table->enum('tax_form_type', ['w9', 'w8ben'])->nullable()->after('user_id');
            // Usamos JSON para flexibilidad absoluta en los campos del formulario
            $table->json('tax_form_data')->nullable()->after('tax_form_type'); 
            
            // 3. Implementamos la máquina de estados
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected', 'suspended'])
                  ->default('pending')
                  ->after('custom_commission_value');
        });

        // 4. Protegemos la data existente: Pasamos los activos a aprobados
        DB::table('org_company_partners')
            ->where('is_active', 1)
            ->update(['status' => 'approved']);

        DB::table('org_company_partners')
            ->where('is_active', 0)
            ->update(['status' => 'suspended']);

        // 5. Limpiamos la estructura vieja
        Schema::table('org_company_partners', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('org_company_partners', function (Blueprint $table) {
            $table->boolean('is_active')->default(1)->after('custom_commission_value');
        });

        DB::table('org_company_partners')
            ->where('status', 'approved')
            ->update(['is_active' => 1]);

        DB::table('org_company_partners')
            ->where('status', '!=', 'approved')
            ->update(['is_active' => 0]);

        Schema::table('org_company_partners', function (Blueprint $table) {
            $table->dropColumn(['status', 'tax_form_type', 'tax_form_data']);
            // Nota: Si haces rollback y hay códigos nulos, esto podría fallar a nivel base de datos.
            $table->string('referral_code')->nullable(false)->change();
        });
    }
};