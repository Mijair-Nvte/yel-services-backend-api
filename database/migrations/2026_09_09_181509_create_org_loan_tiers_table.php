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
        Schema::create('org_loan_tiers', function (Blueprint $table) {
            $table->id();
            
            // Multitenancy y UID
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            
            // Nombre del nivel (Ej. Arranque, Crecimiento, Élite)
            $table->string('name');
            
            // Rangos de volumen FONDEADO mensual para clasificar al partner
            $table->decimal('min_monthly_volume', 15, 2)->default(0)->comment('Volumen mínimo fondeado en el mes');
            $table->decimal('max_monthly_volume', 15, 2)->nullable()->comment('Volumen máximo fondeado en el mes. NULL = sin límite');
            
            // Umbrales para clasificar el tamaño del PRÉSTAMO INDIVIDUAL
            $table->decimal('small_loan_max_amount', 15, 2)->default(149999.99)->comment('Hasta qué monto se considera préstamo chico');
            $table->decimal('large_loan_min_amount', 15, 2)->default(250000.00)->comment('A partir de qué monto se considera préstamo grande');
            
            // UI y Beneficios
            $table->json('features')->nullable()->comment('JSON con los beneficios habilitados para este nivel');
            $table->string('color_theme')->nullable()->comment('Color de tema para el frontend (ej. emerald, indigo, amber)');

            // Reglas de pago según el tamaño del préstamo
            $table->decimal('small_loan_fixed_fee', 10, 2)->comment('Pago fijo para préstamos chicos');
            $table->decimal('medium_loan_percentage', 5, 4)->default(0.0020)->comment('Porcentaje (Ej. 0.0020 para 0.20%) para préstamos medianos y grandes');
            $table->decimal('large_loan_cap', 10, 2)->comment('Tope máximo a pagar si es un préstamo grande');
            
            // Estado y auditoría
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_loan_tiers');
    }
};