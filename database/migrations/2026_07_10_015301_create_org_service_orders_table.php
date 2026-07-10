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
        Schema::create('org_service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique(); // Identificador público único (sro_...)
            
            // Relaciones estructurales del ecosistema
            $table->foreignId('org_company_id')->constrained('org_companies')->onDelete('cascade');
            $table->foreignId('org_sale_id')->constrained('org_sales')->onDelete('cascade');
            $table->foreignId('org_service_id')->constrained('org_services')->onDelete('cascade');
            $table->foreignId('org_customer_id')->constrained('org_customers')->onDelete('cascade');
            
            // El "Owner" principal encargado de ejecutar este trámite específico
            $table->foreignId('assigned_to')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null'); // Si el usuario se elimina, la orden no se borra, queda sin asignar
            
            // Ciclo de vida y estados del pipeline interno
            $table->enum('status', [
                'pending', 
                'in_progress', 
                'waiting_client', 
                'review', 
                'completed', 
                'cancelled'
            ])->default('pending');
            
            // Campo flexible JSON para guardar enlaces de entrega, notas operativas, etc.
            $table->json('metadata')->nullable(); 
            
            $table->timestamps();
            $table->softDeletes(); // Habilita borrado lógico

            // Índices de optimización para el Dashboard y Kanban
            $table->index(['org_company_id', 'status']);
            $table->index('assigned_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_service_orders');
    }
};