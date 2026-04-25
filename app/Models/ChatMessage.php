<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    // Permite llenar: org_company_id, chat_conversation_id, sender_id, body, type
    protected $guarded = [];

    /**
     * Relación directa con la empresa (Multi-tenant)
     */
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
    
    /**
     * Conversación a la que pertenece el mensaje
     */
    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * Usuario que envió el mensaje
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}