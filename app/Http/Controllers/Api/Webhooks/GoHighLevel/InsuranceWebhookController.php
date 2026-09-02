<?php

namespace App\Http\Controllers\Api\Webhooks\GoHighLevel;

use App\Http\Controllers\Controller;
use App\Models\OrgInsuranceApplication;
use App\Services\GhlStatusSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InsuranceWebhookController extends Controller
{
    public function updateStatus(Request $request)
    {
        // Log::info('=== INICIO GHL WEBHOOK [INSURANCES STATUS] ===');
       Log::info('Payload recibido:', $request->all());

        try {
            // OJO: En tu workflow de GHL de seguros, asegúrate de enviar la variable como 'insurance_uid'
            $uid = $request->input('customData.insurance_uid');
            $ghlStatus = $request->input('status') ? strtolower(trim($request->input('status'))) : null;

            if (! $uid) {
                Log::warning('Webhook rechazado: Falta el UID en el payload de seguros.');
                return response()->json(['error' => 'Falta el identificador UID'], 400);
            }

            $insurance = OrgInsuranceApplication::where('uid', $uid)->firstOrFail();

            // Usamos nuestro servicio modular, esta vez pasándole 'insurances'
            GhlStatusSyncService::sync($insurance, $ghlStatus, 'insurances');

            return response()->json(['success' => true, 'message' => 'Estatus de seguro sincronizado correctamente'], 200);

        } catch (\Exception $e) {
            Log::error('Error en InsuranceWebhookController: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }
}