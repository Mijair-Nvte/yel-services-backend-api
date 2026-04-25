<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatParticipant extends Model
{
    use HasFactory;

    // Permite llenar: org_company_id, chat_conversation_id, user_id, last_read_message_id
    protected $guarded = [];

    /**
     * Casts de fechas personalizadas
     */
    protected $casts = [
        'cleared_at' => 'datetime',
    ];

    /**
     * Relación directa con la empresa (Multi-tenant)
     */
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
    
    /**
     * Conversación en la que participa
     */
    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * El usuario que es participante
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * El último mensaje leído por este usuario en esta conversación
     */
    public function lastReadMessage()
    {
        return $this->belongsTo(ChatMessage::class, 'last_read_message_id');
    }
}