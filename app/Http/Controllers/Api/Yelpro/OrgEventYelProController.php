<?php

namespace App\Http\Controllers\Api\Yelpro;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrgEventYelProController extends Controller
{
    /**
     * 📅 Listar eventos filtrados solo para Yel Pro
     */
    public function index(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $userId = Auth::id(); // Obtenemos el ID del usuario logueado

            $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);

            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            $events = OrgEvent::where('org_company_id', $company->id)
                ->where('target_platform', 'yel_pro')
                ->where('is_active', true)
                // 🔥 MAGIA: Añade un campo booleano 'is_attending' a cada evento
                ->withExists(['attendees as is_attending' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->where(function ($query) use ($from, $to) {
                    $query->whereBetween('starts_at', [$from, $to])
                        ->orWhereBetween('ends_at', [$from, $to])
                        ->orWhere(function ($q) use ($from, $to) {
                            $q->where('starts_at', '<=', $from)
                                ->where('ends_at', '>=', $to);
                        });
                })
                ->orderBy('starts_at')
                ->get();

            return response()->json($events);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar eventos de Yel Pro.'], 500);
        }
    }

    /**
     * 🙋 Confirmar o Cancelar Asistencia
     */
    public function toggleAttendance(string $uid, string $eventUid)
    {
        try {
            // Validamos que la compañía exista
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            
            // Validamos que el evento exista y pertenezca a esa compañía (Seguridad)
            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            // Usamos toggle(): Si no existe lo asocia, si ya existe lo quita.
            $result = $event->attendees()->toggle(Auth::id());

            // Si el arreglo 'attached' tiene algo, significa que confirmó. Si no, canceló.
            $isAttending = count($result['attached']) > 0;

            return response()->json([
                'message' => $isAttending ? 'Asistencia confirmada con éxito 🎉' : 'Asistencia cancelada',
                'is_attending' => $isAttending
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al procesar la asistencia.'], 500);
        }
    }
}