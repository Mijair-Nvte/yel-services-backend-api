<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_company_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // El código único que se usará en la URL (ej: yelservices.com?ref=mijair-8x2a)
            $table->string('referral_code')->unique();
            
            // Preparado para el futuro (aunque ahora el contador lo asigne manual)
            // Si el día de mañana quieres automatizarlo, ya tienes los campos listos.
            $table->enum('custom_commission_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('custom_commission_value', 10, 2)->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Clave única compuesta: Un usuario solo puede ser "partner" una vez por compañía
            $table->unique(['org_company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_company_partners');
    }
};