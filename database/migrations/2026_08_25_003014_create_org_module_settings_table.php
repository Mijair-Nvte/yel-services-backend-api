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
        Schema::create('org_module_settings', function (Blueprint $table) {
            $table->id();
            
            // Relación con tu tabla de compañías (tenant). 
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            
            // El nombre del módulo (ej: 'loans', 'insurances', 'sales'). 
            // Lo indexamos porque siempre buscaremos por este campo junto con el ID de la compañía.
            $table->string('module_name')->index();
            
            // El corazón de la escalabilidad: Aquí vivirá el JSON con los checkboxes, correos, etc.
            $table->json('settings')->nullable();
            
            // Un switch maestro por si alguna vez necesitas apagar las notificaciones de un módulo temporalmente
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            // REGLA DE ORO DE ARQUITECTURA:
            // Asegura a nivel de base de datos que una compañía solo pueda tener 
            // UN ÚNICO registro de configuración por módulo.
            $table->unique(['org_company_id', 'module_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_module_settings');
    }
};