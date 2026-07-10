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
        Schema::create('org_service_order_followers', function (Blueprint $table) {
            $table->id();
            
            // Relación a la orden de servicio específica
            $table->foreignId('org_service_order_id')
                  ->constrained('org_service_orders')
                  ->onDelete('cascade'); // Si se elimina la orden (hard delete), se limpian sus seguidores
            
            // Relación al usuario del equipo que dará apoyo/seguimiento
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
                  
            $table->timestamps();

            // Índice compuesto único para evitar duplicar el mismo seguidor en la misma orden
            $table->unique(['org_service_order_id', 'user_id'], 'service_order_follower_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_service_order_followers');
    }
};