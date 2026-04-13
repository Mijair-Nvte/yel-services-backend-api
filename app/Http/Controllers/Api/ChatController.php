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
    // 1. Listar todos los chats del usuario en la empresa actual
    public function index($uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $user = request()->user();

        $conversations = ChatConversation::where('org_company_id', $company->id)
            ->whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                // 🔥 Cargamos participantes -> usuario -> perfil (para el avatar_url)
                'participants.user' => function ($q) {
                    $q->select('id', 'name', 'email')->with('profile:user_id,avatar');
                },
                // ✅ Mantenemos el último mensaje para la vista previa
                'lastMessage',
            ])
            // Ordenamos por los chats que tienen actividad más reciente
            ->orderByDesc(
                ChatMessage::select('created_at')
                    ->whereColumn('chat_conversation_id', 'chat_conversations.id')
                    ->latest()
                    ->take(1)
            )
            ->get();

        return response()->json($conversations);
    }

    // 2. Obtener un chat 1a1 existente o crearlo si es la primera vez que hablan
    public function getOrCreateDirect($uid, $targetUserId)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $user = request()->user();

        if ($user->id == $targetUserId) {
            return response()->json(['message' => 'No puedes crear un chat contigo mismo'], 400);
        }

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
            DB::transaction(function () use (&$conversation, $company, $user, $targetUserId) {
                $conversation = ChatConversation::create([
                    'org_company_id' => $company->id,
                    'type' => 'direct',
                ]);

                ChatParticipant::insert([
                    ['chat_conversation_id' => $conversation->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()],
                    ['chat_conversation_id' => $conversation->id, 'user_id' => $targetUserId, 'created_at' => now(), 'updated_at' => now()],
                ]);
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
            // 🔥 También aquí cargamos el perfil para que el Header del chat tenga foto
            'conversation' => $conversation->load(['participants.user' => function ($q) {
                $q->select('id', 'name', 'email')->with('profile:user_id,avatar');
            }]),
            'messages' => $messages,
        ]);
    }

    // 3. Enviar un mensaje
    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate(['body' => 'required|string']);
        $user = request()->user();

        // Validar que el usuario pertenezca a esta conversación
        $participant = ChatParticipant::where('chat_conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $message = ChatMessage::create([
            'chat_conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'body' => $request->body,
        ]);

        // Auto-marcar como leído para el que lo envía
        $participant->update(['last_read_message_id' => $message->id]);

        // 🔥 Disparar el evento de Websockets hacia el Frontend
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
