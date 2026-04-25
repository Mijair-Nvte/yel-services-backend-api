<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
{
    use HasFactory, SoftDeletes;

    // Permite que todas las columnas (id, org_company_id, type, etc.) se llenen masivamente
    protected $guarded = [];

    /**
     * Relación con la compañía (Organización)
     */
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Participantes del chat
     */
    public function participants()
    {
        return $this->hasMany(ChatParticipant::class, 'chat_conversation_id');
    }

    /**
     * Historial de mensajes
     */
    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'chat_conversation_id');
    }

    /**
     * Helper: Obtener el último mensaje para la lista de chats
     */
    public function lastMessage()
    {
        return $this->hasOne(ChatMessage::class, 'chat_conversation_id')->latestOfMany();
    }
}