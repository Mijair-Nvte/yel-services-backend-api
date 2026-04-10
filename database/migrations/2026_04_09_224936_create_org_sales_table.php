<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_sales', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();

            // --- Datos crudos del Webhook (Go High Level) ---
            $table->string('source_type')->nullable(); // Ej: 'payment_link' o 'store'
            $table->string('source_id')->nullable()->index(); // Ej: '69cd52dcc6a0e600f4d06e97'
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('product_name')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0); // Ej: 350.00

            // --- Lógica de Negocio (Comisiones del 8%) ---
            // nullOnDelete para que si borras a un vendedor, la venta histórica no se borre
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('commission_amount', 10, 2)->default(0); // Ej: 28.00
            
            // Estados: 'pending' (pendiente), 'paid' (pagado), 'not_applicable' (ventas de tienda)
            $table->enum('commission_status', ['pending', 'paid', 'not_applicable'])->default('not_applicable');

            // --- Extra: Tramitador (5%) que mencionaste antes ---
            // Lo dejo preparado por si el contador necesita asignar a alguien más al trámite
            $table->foreignId('processor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('processor_commission_amount', 10, 2)->default(0);
            $table->enum('processor_commission_status', ['pending', 'paid', 'not_applicable'])->default('not_applicable');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_sales');
    }
};