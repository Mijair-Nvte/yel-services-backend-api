<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Usuario destinatario
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Multi-tenant (tu sistema usa org_company)
            $table->foreignId('org_company_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            // Tipo de notificación (flexible y escalable)
            $table->string('type', 100);

            // Datos dinámicos (JSON)
            $table->json('data');

            // Estado
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Índices importantes (performance 🔥)
            $table->index(['user_id', 'read_at']);
            $table->index(['org_company_id']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
