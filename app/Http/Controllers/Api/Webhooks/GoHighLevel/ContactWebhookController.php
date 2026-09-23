<?php

namespace App\Http\Controllers\Api\Webhooks\GoHighLevel;

use App\Http\Controllers\Controller;
use App\Jobs\GoHighLevel\ProcessContactWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactWebhookController extends Controller
{
    public function handleContact(Request $request)
    {
        try {
            $payload = $request->all();
            
            // ==========================================
            // 🐛 LOG PARA DEPURAR EL PAYLOAD DE GHL
            // ==========================================
            Log::info('=== INICIO GHL WEBHOOK [CONTACTS] ===');
            Log::info('Payload recibido de GoHighLevel:', $payload);
            
            // Validamos mínimamente que venga información
            if (empty($payload)) {
                Log::warning('GHL Webhook de contacto rechazado: Payload vacío.');
                return response()->json(['error' => 'Payload vacío'], 400);
            }

            // Despachamos el Job en segundo plano (Queue)
            ProcessContactWebhookJob::dispatch($payload);

            return response()->json(['success' => true, 'message' => 'Webhook recibido y encolado'], 200);

        } catch (\Exception $e) {
            Log::error('Error en ContactWebhookController: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }
}