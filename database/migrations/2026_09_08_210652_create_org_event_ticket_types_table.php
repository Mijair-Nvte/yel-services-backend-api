<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_event_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            $table->foreignId('org_event_id')->constrained('org_events')->cascadeOnDelete();
            $table->foreignId('org_ticket_type_id')->constrained('org_ticket_types')->cascadeOnDelete();
            
            $table->integer('capacity')->default(0); 
            $table->decimal('price', 10, 2)->default(0.00); 
            
            // Columna JSON para configuraciones específicas de este boleto en ESTE evento
            $table->json('metadata')->nullable(); 
            
            $table->timestamps();

            // Súper importante: Evitar que por error se asigne dos veces el mismo tipo de boleto al mismo evento
            $table->unique(['org_event_id', 'org_ticket_type_id'], 'event_ticket_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_event_ticket_types');
    }
};