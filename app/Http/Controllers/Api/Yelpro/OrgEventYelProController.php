<?php

namespace App\Http\Controllers\Api\Yelpro;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrgEventYelProController extends Controller
{
    /**
     * 📅 Listar eventos filtrados solo para Yel Pro
     */
    public function index(Request $request, string $uid)
    {
        try {
            // Buscamos la compañía
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            
            // Aquí puedes omitir authorizeWorkspace si esta ruta es pública para usuarios de Yel Pro
            // o dejarla si solo miembros autorizados pueden verlos.

            $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);

            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            // 🔥 AQUÍ ESTÁ EL AJUSTE CLAVE
            $events = OrgEvent::where('org_company_id', $company->id)
                ->where('target_platform', 'yel_pro') // Filtro específico
                ->where('is_active', true)
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
}