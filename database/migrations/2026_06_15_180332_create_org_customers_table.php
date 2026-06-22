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
        Schema::create('org_customers', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            
            // Relación Multi-tenant
            $table->foreignId('org_company_id')
                  ->constrained('org_companies')
                  ->cascadeOnDelete();

            // Directorio puro: Solo los datos de contacto reales
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            
            // Campo de escape: para guardar tags, notas o preferencias 
            // sin tener que alterar la estructura de la tabla en el futuro.
            $table->json('metadata')->nullable()->comment('Datos extra flexibles');

            $table->timestamps();
            $table->softDeletes(); // Vital para mantener el historial en org_sales si borras el cliente

            // Índice para buscar rápidamente si un cliente ya existe al registrar una venta
            $table->index(['org_company_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_customers');
    }
};