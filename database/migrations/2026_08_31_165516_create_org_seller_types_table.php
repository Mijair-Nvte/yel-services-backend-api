<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_seller_types', function (Blueprint $table) {
            $table->id();
            
            // Relación Multi-tenant
            $table->foreignId('org_company_id')
                  ->constrained('org_companies') // Ajusta el nombre de tu tabla de compañías si es diferente
                  ->cascadeOnDelete();
            
            $table->string('name')->comment('Ej: Interno, Externo, Agencia');
            $table->string('slug')->comment('Ej: internal, external, agency');
            $table->text('description')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();

            // Para evitar que se duplique un slug en la misma compañía
            $table->unique(['org_company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_seller_types');
    }
};