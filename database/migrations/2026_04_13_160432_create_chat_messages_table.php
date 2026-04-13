<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            // Útil si después quieres mandar imágenes, archivos o mensajes del sistema
            $table->string('type')->default('text');
            $table->timestamps();
            $table->softDeletes(); // Para tu requerimiento de "eliminar mensaje"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
