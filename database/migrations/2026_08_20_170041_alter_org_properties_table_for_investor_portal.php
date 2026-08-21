<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('org_properties', function (Blueprint $table) {
            // 1. Eliminar campos innecesarios
            $table->dropColumn(['investment_amount', 'cash_flow_status']);

            // 2. Agregar nuevos campos requeridos (después de columnas existentes para mantener orden)
            $table->string('property_type')->nullable()->after('portfolio_type')->comment('Ej: Single Family, Multi Family, etc.');
            
            $table->string('borrower_first_name')->nullable()->after('closing_type');
            $table->string('borrower_last_name')->nullable()->after('borrower_first_name');
            $table->string('co_borrower_first_name')->nullable()->after('borrower_last_name');
            $table->string('co_borrower_last_name')->nullable()->after('co_borrower_first_name');
            $table->string('borrower_mobile', 50)->nullable()->after('co_borrower_last_name');
            
            $table->string('address')->nullable()->after('borrower_mobile');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('zip', 20)->nullable()->after('state');
            $table->string('occupancy', 100)->nullable()->after('zip')->comment('Ej: Primary Residence, Investment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_properties', function (Blueprint $table) {
            // Revertir: Eliminar los campos agregados
            $table->dropColumn([
                'property_type',
                'borrower_first_name',
                'borrower_last_name',
                'co_borrower_first_name',
                'co_borrower_last_name',
                'borrower_mobile',
                'address',
                'city',
                'state',
                'zip',
                'occupancy'
            ]);

            // Revertir: Volver a agregar los campos eliminados
            $table->decimal('investment_amount', 12, 2)->default(0.00)->after('closing_type');
            $table->string('cash_flow_status')->nullable()->after('investment_amount');
        });
    }
};