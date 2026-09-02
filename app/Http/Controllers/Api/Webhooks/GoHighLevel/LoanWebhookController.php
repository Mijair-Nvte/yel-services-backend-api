<?php

namespace App\Http\Controllers\Api\Webhooks\GoHighLevel;

use App\Http\Controllers\Controller;
use App\Models\OrgLoanApplication;
use App\Services\GhlStatusSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoanWebhookController extends Controller
{
    public function updateStatus(Request $request)
    {
        //Log::info('=== INICIO GHL WEBHOOK [LOANS STATUS] ===');
        //Log::info('Payload recibido:', $request->all());

        try {
            $uid = $request->input('customData.loan_uid');
            $ghlStatus = $request->input('status') ? strtolower(trim($request->input('status'))) : null;

            if (! $uid) {
                Log::warning('Webhook rechazado: Falta el UID en el payload.');
                return response()->json(['error' => 'Falta el identificador UID'], 400);
            }

            $loan = OrgLoanApplication::where('uid', $uid)->firstOrFail();

            // Usamos nuestro servicio modular y escalable
            GhlStatusSyncService::sync($loan, $ghlStatus, 'loans');

            return response()->json(['success' => true, 'message' => 'Estatus sincronizado correctamente'], 200);

        } catch (\Exception $e) {
            Log::error('Error en LoanWebhookController: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }
}