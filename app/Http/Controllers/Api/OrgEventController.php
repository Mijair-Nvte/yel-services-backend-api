<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrgEventController extends Controller
{
    use AuthorizesWorkspace;

    /**
     * Listar eventos por rango de fechas
     * GET /org-companies/{uid}/events?from=2026-02-01&to=2026-02-28
     */
    public function index(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $this->authorizeWorkspace($company);

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        // 🔥 FIX: Transformamos las fechas para que abarquen el día completo
        // Esto soluciona el problema de la vista "Day" donde from y to son el mismo día.
        $from = Carbon::parse($request->from)->startOfDay(); // 00:00:00
        $to = Carbon::parse($request->to)->endOfDay();       // 23:59:59

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
    }

    /**
     * Crear evento
     */
    public function store(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $this->authorizeWorkspace($company);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|in:blue,red,green,yellow,purple,orange,pink',
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|url|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_all_day' => 'boolean',
        ]);

        if (! isset($data['color'])) {
            $data['color'] = 'blue';
        }

        $event = OrgEvent::create([
            ...$data,
            'org_company_id' => $company->id,
            'created_by' => Auth::id(),
        ]);

        return response()->json($event, 201);
    }

    /**
     * Mostrar un evento
     */
    public function show(string $uid, string $eventUid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $this->authorizeWorkspace($company);

        $event = OrgEvent::where('uid', $eventUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        return response()->json($event);
    }

    /**
     * Actualizar evento
     */
    public function update(Request $request, string $uid, string $eventUid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $this->authorizeWorkspace($company);

        $event = OrgEvent::where('uid', $eventUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|in:blue,red,green,yellow,purple,orange,pink',
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|url|max:255',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_all_day' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if (! isset($data['color'])) {
            $data['color'] = 'blue';
        }
        
        $event->update($data);

        return response()->json($event);
    }

    /**
     * Eliminar evento
     */
    public function destroy(string $uid, string $eventUid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $this->authorizeWorkspace($company);

        $event = OrgEvent::where('uid', $eventUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        $event->delete();

        return response()->json([
            'message' => 'Evento eliminado correctamente',
        ]);
    }
}
