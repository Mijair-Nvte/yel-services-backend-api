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
        Schema::create('org_time_trackings', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique(); // Ej: ttk_01HGW...
            $table->unsignedBigInteger('org_company_id');
            $table->unsignedBigInteger('user_id');
            
            // Tiempos
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable()->comment('Calculado al finalizar para optimizar reportes');
            
            // Estado y Auditoría
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable()->comment('Notas opcionales del usuario sobre su día');

            $table->timestamps();

            // Llaves foráneas
            $table->foreign('org_company_id')->references('id')->on('org_companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Índices para escalabilidad y búsquedas rápidas en el dashboard
            $table->index(['org_company_id', 'user_id', 'status']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_time_trackings');
    }
};