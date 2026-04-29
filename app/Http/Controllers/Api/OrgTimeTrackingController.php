<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgTimeTracking;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OrgTimeTrackingController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * Listado de registros (Historial)
     */
    public function index(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            $query = OrgTimeTracking::with('user:id,name')
                ->where('org_company_id', $company->id);

            // Si NO es admin, solo ve sus propios registros
            if (! auth()->user()->hasPermissionTo('manage_time_tracking')) {
                $query->where('user_id', auth()->id());
            }

            // Filtros opcionales (por fecha o usuario)
            if ($request->has('user_id') && auth()->user()->hasPermissionTo('manage_time_tracking')) {
                $query->where('user_id', $request->user_id);
            }

            $history = $query->latest('started_at')->paginate(15);

            return response()->json($history, 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el historial.'], 500);
        }
    }

    /**
     * Obtener el estado actual del usuario (¿Está conectado o no?)
     */
    public function currentStatus(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            // Todos los miembros pueden ver su propio estado, no validamos un permiso estricto aquí

            $activeSession = OrgTimeTracking::where('org_company_id', $company->id)
                ->where('user_id', auth()->id())
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->first();

            return response()->json([
                'is_tracking' => (bool) $activeSession,
                'data' => $activeSession,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener estado del tracking.'], 500);
        }
    }

    /**
     * Iniciar el día (Check-in)
     */
    public function checkIn(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            // Verificar si ya tiene una sesión activa para evitar duplicados
            $activeSession = OrgTimeTracking::where('org_company_id', $company->id)
                ->where('user_id', auth()->id())
                ->where('status', 'active')
                ->first();

            if ($activeSession) {
                return response()->json(['message' => 'Ya tienes una sesión activa.'], 400);
            }

            $tracking = OrgTimeTracking::create([
                'org_company_id' => $company->id,
                'user_id' => auth()->id(),
                'started_at' => Carbon::now(),
                'status' => 'active',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Check-in registrado exitosamente.', 'data' => $tracking], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al hacer check-in.'], 500);
        }
    }

    /**
     * Finalizar el día (Check-out)
     */
    public function checkOut(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            $activeSession = OrgTimeTracking::where('org_company_id', $company->id)
                ->where('user_id', auth()->id())
                ->where('status', 'active')
                ->firstOrFail();

            $endedAt = Carbon::now();
            $startedAt = Carbon::parse($activeSession->started_at);

            // Calculamos la duración exacta en minutos
            $durationMinutes = $startedAt->diffInMinutes($endedAt);

            $activeSession->update([
                'ended_at' => $endedAt,
                'duration_minutes' => $durationMinutes,
                'status' => 'completed',
                'notes' => $request->input('notes'), // Opcional, por si quieren dejar un reporte del día
            ]);

            return response()->json(['message' => 'Check-out registrado exitosamente.', 'data' => $activeSession], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'No se encontró una sesión activa para finalizar.'], 404);
        }
    }
}
