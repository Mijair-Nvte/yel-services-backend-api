<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_services', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique(); // Manteniendo tu estándar de UIDs (ej. srv_...)
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Integración con Stripe
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();

            // Para el futuro: Si decides automatizar comisiones
            $table->enum('default_commission_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('default_commission_value', 10, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes(); // Buena práctica para no perder el historial de ventas si borras un servicio
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_services');
    }
};
