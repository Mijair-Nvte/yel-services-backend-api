<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatParticipant;

Broadcast::channel('user.{id}', function ($user, $id) {
    \Log::info('CHANNEL AUTH', [
        'auth_user_id' => $user->id,
        'channel_id' => $id,
    ]);

    return true; // 🔥 FORZAR
});

Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    // Es mejor validar que el usuario realmente pertenezca a la conversación
    // para que nadie pueda espiar chats ajenos.
    return ChatParticipant::where('chat_conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});
