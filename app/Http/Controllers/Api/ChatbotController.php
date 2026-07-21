<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ChatbotService;

class ChatbotController extends Controller
{
    public function ask(Request $request, ChatbotService $chatbotService)
    {
        // Validamos que Next.js nos envíe un array de mensajes
        $validated = $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|in:user,assistant,system',
            'messages.*.content' => 'required|string',
        ]);

        try {
            $reply = $chatbotService->getChatResponse($validated['messages']);
            
            return response()->json([
                'success' => true,
                'reply' => $reply
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al comunicar con el soporte.'
            ], 500);
        }
    }
}