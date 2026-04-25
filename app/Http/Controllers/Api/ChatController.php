<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\OrgCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * 1. Listar chats del usuario en la empresa actual
     */
    public function index($uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $user = auth()->user();

        $conversations = ChatConversation::where('org_company_id', $company->id)
            ->whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'participants.user' => function ($q) {
                    $q->select('id', 'name', 'email')->with('profile:user_id,avatar');
                },
                'lastMessage',
            ])
            ->orderByDesc(
                ChatMessage::select('created_at')
                    ->whereColumn('chat_conversation_id', 'chat_conversations.id')
                    ->latest()
                    ->take(1)
            )
            ->get();

        return response()->json($conversations);
    }

    /**
     * 2. Obtener o Crear chat 1a1 (Contextual)
     */
    public function getOrCreateDirect($uid, $targetUserId)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $user = auth()->user();

        if ($user->id == $targetUserId) {
            return response()->json(['message' => 'No puedes crear un chat contigo mismo'], 400);
        }

        // Buscamos la conversación en esta empresa específica
        $conversation = ChatConversation::where('org_company_id', $company->id)
            ->where('type', 'direct')
            ->whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereHas('participants', function ($query) use ($targetUserId) {
                $query->where('user_id', $targetUserId);
            })
            ->first();

        if (! $conversation) {
            $conversation = DB::transaction(function () use ($company, $user, $targetUserId) {
                $conv = ChatConversation::create([
                    'org_company_id' => $company->id, // Ya lo tenías
                    'type' => 'direct',
                ]);

                // AHORA: Insertamos con el org_company_id
                ChatParticipant::insert([
                    [
                        'org_company_id' => $company->id,
                        'chat_conversation_id' => $conv->id,
                        'user_id' => $user->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ],
                    [
                        'org_company_id' => $company->id,
                        'chat_conversation_id' => $conv->id,
                        'user_id' => $targetUserId,
                        'created_at' => now(), 'updated_at' => now(),
                    ],
                ]);

                return $conv;
            });
        }

        $myParticipant = ChatParticipant::where('chat_conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        $messages = ChatMessage::where('chat_conversation_id', $conversation->id)
            ->when($myParticipant->cleared_at, function ($query, $clearedAt) {
                $query->where('created_at', '>', $clearedAt);
            })
            ->with('sender:id,name')
            ->latest()
            ->paginate(50);

        return response()->json([
            'conversation' => $conversation->load(['participants.user' => function ($q) {
                $q->select('id', 'name', 'email')->with('profile:user_id,avatar');
            }]),
            'messages' => $messages,
        ]);
    }

    /**
     * 3. Enviar mensaje
     */
    public function sendMessage(Request $request, $uid, $conversationId)
    {
        $request->validate(['body' => 'required|string']);
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $user = auth()->user();

        // Filtramos por empresa para seguridad máxima
        $participant = ChatParticipant::where('chat_conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        $message = ChatMessage::create([
            'org_company_id' => $company->id,
            'chat_conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'body' => $request->body,
        ]);

        $participant->update(['last_read_message_id' => $message->id]);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message->load('sender:id,name'), 201);
    }

    // 4. Marcar mensajes como leídos
    public function markAsRead($conversationId)
    {
        $user = request()->user();

        $latestMessage = ChatMessage::where('chat_conversation_id', $conversationId)->latest('id')->first();

        if ($latestMessage) {
            ChatParticipant::where('chat_conversation_id', $conversationId)
                ->where('user_id', $user->id)
                ->update(['last_read_message_id' => $latestMessage->id]);
        }

        return response()->json(['message' => 'Chat marcado como leído']);
    }

    // 5. Eliminar un mensaje específico (Solo el dueño)
    public function deleteMessage($messageId)
    {
        $user = request()->user();
        $message = ChatMessage::where('id', $messageId)->where('sender_id', $user->id)->firstOrFail();

        $message->delete(); // Soft delete gracias a la migración

        return response()->json(['message' => 'Mensaje eliminado']);
    }

    // 6. "Eliminar/Vaciar" el chat para el usuario actual
    public function clearConversation($conversationId)
    {
        $user = request()->user();

        // En lugar de borrar mensajes (lo que afectaría a la otra persona),
        // simplemente actualizamos la fecha de 'cleared_at'.
        // Así, al traer mensajes, solo mostraremos los posteriores a esta fecha.
        ChatParticipant::where('chat_conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->update(['cleared_at' => now()]);

        return response()->json(['message' => 'Historial de chat vaciado']);
    }

    // 7. Editar un mensaje (Solo si no ha sido leído)
    public function updateMessage(Request $request, $messageId)
    {
        $request->validate(['body' => 'required|string']);
        $user = request()->user();

        $message = ChatMessage::where('id', $messageId)
            ->where('sender_id', $user->id)
            ->firstOrFail();

        // VALIDACIÓN ESTRELLA: Revisamos si alguien más en el chat ya superó o igualó este ID de mensaje
        $hasBeenRead = ChatParticipant::where('chat_conversation_id', $message->chat_conversation_id)
            ->where('user_id', '!=', $user->id)
            ->where('last_read_message_id', '>=', $message->id)
            ->exists();

        if ($hasBeenRead) {
            return response()->json(['message' => 'No puedes editar un mensaje que ya ha sido leído'], 403);
        }

        $message->update(['body' => $request->body]);

        // Disparamos el evento para que Pusher actualice el front
        broadcast(new \App\Events\MessageEdited($message))->toOthers();

        return response()->json($message->load('sender:id,name'));
    }
}
