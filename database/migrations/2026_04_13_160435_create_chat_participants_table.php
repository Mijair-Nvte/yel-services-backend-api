<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Truco de escalabilidad para el "Visto"
            $table->foreignId('last_read_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();

            $table->timestamps();
            // Si un usuario le da "Eliminar chat", en lugar de borrar la conversación entera (afectando a la otra persona),
            // solo eliminamos suavemente su participación o limpiamos su vista.
            $table->timestamp('cleared_at')->nullable();

            // Evitar que el mismo usuario se agregue dos veces a la misma conversación
            $table->unique(['chat_conversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_participants');
    }
};
