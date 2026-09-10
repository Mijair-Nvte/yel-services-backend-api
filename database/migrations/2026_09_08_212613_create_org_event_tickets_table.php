<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_event_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique(); // Hash único para el código QR (se genera al vender o reservar)
            
            // Relaciones estructurales
            $table->foreignId('org_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_event_ticket_type_id')->constrained()->cascadeOnDelete();
            
            // Relaciones comerciales (pueden ser nulas si el boleto está disponible o recién reservado sin loguear)
            $table->foreignId('org_customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('org_sale_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Ciclo de vida y control de inventario
            $table->enum('status', ['available', 'reserved', 'sold', 'scanned', 'cancelled'])
                  ->default('available');
                  
            $table->timestamp('reserved_until')->nullable(); // Clave para liberar los boletos si pasan los 5 minutos sin pagar

            // Datos del asistente real (por si el comprador adquiere para terceros)
            $table->string('attendee_first_name')->nullable();
            $table->string('attendee_last_name')->nullable();
            $table->string('attendee_email')->nullable();
            
            // Control de acceso al evento
            $table->timestamp('scanned_at')->nullable(); 
            
            $table->timestamps();
            $table->softDeletes();

            // Índices para mejorar el rendimiento al buscar boletos disponibles o expirados rápidamente
            $table->index(['org_event_ticket_type_id', 'status']);
            $table->index('reserved_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_event_tickets');
    }
};