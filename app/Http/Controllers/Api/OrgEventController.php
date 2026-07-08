<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrgEventController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📅 Listar eventos por rango
     */
    public function index(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_calendar');

            $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);

            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

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
     * 📝 Crear evento
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            $data = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'color' => 'nullable|string|in:blue,red,green,yellow,purple,orange,pink',
                'location' => 'nullable|string|max:255',
                'meeting_url' => 'nullable|url|max:255',
                'external_url' => 'nullable|url|max:255',
                'target_platform' => 'required|string|in:yel_services,yel_pro,yel_investor',
                'starts_at' => 'required|date',
                'ends_at' => 'nullable|date|after_or_equal:starts_at',
                'is_all_day' => 'boolean',
            ]);

            $event = OrgEvent::create([
                ...$data,
                'org_company_id' => $company->id,
                'created_by' => Auth::id(),
                'color' => $data['color'] ?? 'blue',
            ]);

            return response()->json($event, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear evento.'], 500);
        }
    }

    /**
     * 🔍 Mostrar detalle (Normalizado)
     */
    public function show(string $uid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_calendar');

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json($event);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Evento no encontrado.'], 404);
        }
    }

    /**
     * ✏️ Actualizar (Normalizado)
     */
    public function update(Request $request, string $uid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $data = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'color' => 'nullable|string|in:blue,red,green,yellow,purple,orange,pink',
                'location' => 'nullable|string|max:255',
                'meeting_url' => 'nullable|url|max:255',
                'target_platform' => 'sometimes|string|in:yel_services,yel_pro,yel_investor',
                'starts_at' => 'sometimes|date',
                'ends_at' => 'nullable|date|after_or_equal:starts_at',
                'is_all_day' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $event->update($data);

            return response()->json($event);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar evento.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar (Normalizado)
     */
    public function destroy(string $uid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $event->delete();

            return response()->json(['message' => 'Evento eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar evento.'], 500);
        }
    }
}
