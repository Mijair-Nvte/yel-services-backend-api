<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            // Vinculamos el chat a la compañía basándome en tu estructura actual
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            // 'direct' para 1 a 1, 'group' para el futuro
            $table->enum('type', ['direct', 'group'])->default('direct');
            $table->timestamps();
            $table->softDeletes(); // Para eliminar un chat completo sin borrar los registros físicos
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
