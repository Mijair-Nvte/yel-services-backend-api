<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = []; // Permite asignación masiva

    // Relación con la compañía (Organización)
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    // Participantes del chat
    public function participants()
    {
        return $this->hasMany(ChatParticipant::class, 'chat_conversation_id');
    }

    // Historial de mensajes
    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'chat_conversation_id');
    }

    // Helper: Obtener el último mensaje (muy útil para la lista de chats)
    public function lastMessage()
    {
        return $this->hasOne(ChatMessage::class, 'chat_conversation_id')->latestOfMany();
    }
}
