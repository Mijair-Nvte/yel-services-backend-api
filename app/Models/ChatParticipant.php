<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatParticipant extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Convertir a fecha automáticamente
    protected $casts = [
        'cleared_at' => 'datetime',
    ];

    // Conversación a la que está unido
    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    // El usuario participante
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // El último mensaje que este participante vio
    public function lastReadMessage()
    {
        return $this->belongsTo(ChatMessage::class, 'last_read_message_id');
    }
}
