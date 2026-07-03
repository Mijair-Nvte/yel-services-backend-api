<?php

namespace App\Http\Controllers\Api\Partner\Calendar;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * 📅 Listar eventos por rango (Solo lectura para afiliados)
     */
    public function index(Request $request, string $companyUid)
    {
        try {
            // Buscamos la compañía por su UID
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);

            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            // Obtenemos los eventos sin verificar permisos de Spatie
            $events = OrgEvent::where('org_company_id', $company->id)
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
            return response()->json(['message' => 'Error al cargar eventos.'], 500);
        }
    }

    /**
     * 🔍 Mostrar detalle de un evento (Solo lectura para afiliados)
     */
    public function show(string $companyUid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json($event);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Evento no encontrado.'], 404);
        }
    }
}