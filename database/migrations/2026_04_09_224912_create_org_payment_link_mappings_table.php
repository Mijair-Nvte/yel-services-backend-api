<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_payment_link_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique(); // El ID único que usas en todas tus tablas
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            
            // El usuario que es dueño de este link (El Vendedor)
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete(); 
            
            // Datos del Link
            $table->string('ghl_payment_link_id')->index(); // Ej: '69cd52dcc6a0e600f4d06e97'
            $table->string('service_name'); // Ej: 'CREA TU LLC'
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            // Un mismo link no debería estar asignado a dos personas en la misma empresa
            $table->unique(['org_company_id', 'ghl_payment_link_id'], 'org_company_link_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_payment_link_mappings');
    }
};