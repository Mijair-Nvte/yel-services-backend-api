<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Usamos ShouldBroadcastNow para que sea instantáneo y no requiera colas (jobs) por ahora
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    // Pasamos el mensaje recién creado al evento
    public function __construct(ChatMessage $message)
    {
        // Cargamos el remitente para que Next.js sepa quién lo mandó sin hacer otra petición
        $this->message = $message->load('sender:id,name,email');
    }

    // Aquí le decimos a Pusher en qué canal emitir.
    // Usamos un PrivateChannel aislando por el ID de la conversación.
    public function broadcastOn(): array
    {
        // 1. Canal local de la conversación (para actualizar la pantalla si el chat está abierto)
        $channels = [
            new PrivateChannel('chat.'.$this->message->chat_conversation_id),
        ];

        // 2. 🔥 NUEVO: Canal GLOBAL del destinatario (para notificaciones en toda la app)
        // Buscamos a los participantes de este chat (excluyendo al que lo envió)
        $participants = \App\Models\ChatParticipant::where('chat_conversation_id', $this->message->chat_conversation_id)
            ->where('user_id', '!=', $this->message->sender_id)
            ->get();

        foreach ($participants as $participant) {
            // Añadimos el canal de cada destinatario (el mismo que usas para notificaciones)
            $channels[] = new PrivateChannel('user.'.$participant->user_id);
        }

        return $channels;
    }

    // El nombre del evento que Next.js va a escuchar
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
