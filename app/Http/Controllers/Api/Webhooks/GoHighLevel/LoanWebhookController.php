<?php

namespace App\Http\Controllers\Api\Webhooks\GoHighLevel;

use App\Http\Controllers\Controller;
use App\Models\OrgLoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class LoanWebhookController extends Controller
{
    public function updateStatus(Request $request)
    {
        Log::info('=== INICIO GHL WEBHOOK [LOANS STATUS] ===');
        Log::info('Payload recibido:', $request->all());

        try {
            $uid = $request->input('customData.loan_uid');
            $ghlStatus = $request->input('status') ? strtolower(trim($request->input('status'))) : null;

            if (! $uid) {
                Log::warning('Webhook rechazado: Falta el UID en el payload.');
                return response()->json(['error' => 'Falta el identificador UID'], 400);
            }

            $loan = OrgLoanApplication::where('uid', $uid)->firstOrFail();

            $newStatus = null;
            switch ($ghlStatus) {
                case 'won':
                    $newStatus = 'Won';
                    break;
                case 'lost':
                    $newStatus = 'Lost';
                    break;
                case 'abandoned':
                    $newStatus = 'Abandon'; // Coincide exactamente con el ENUM de la BD
                    break;
                case 'open':
                    $newStatus = 'Open';
                    break;
            }

            if ($newStatus && $loan->status !== $newStatus) {
                // Actualiza y dispara automáticamente el Observer limpio
                $loan->update([
                    'status' => $newStatus,
                ]);
                Log::info("Éxito: Préstamo {$uid} actualizado a estatus: {$newStatus}");
            }

            return response()->json(['success' => true, 'message' => 'Webhook procesado correctamente'], 200);

        } catch (\Exception $e) {
            Log::error('Error en LoanWebhookController: ' . $e->getMessage());

            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }
}