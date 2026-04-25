<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgArea;
use App\Models\OrgAreaUserRole;
use App\Models\OrgCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgAreaUserRoleController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * Crear asignación (Contextual a la empresa)
     */
    public function store(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);
        $this->authorize('manage_areas');

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'org_area_id' => 'required|exists:org_areas,id',
            'org_role_id' => 'required|exists:org_positions,id',
            'position_title' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Validar que el área pertenezca a esta empresa
        $area = OrgArea::where('id', $data['org_area_id'])
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        // Validar que el usuario sea miembro de la empresa
        $isMember = $company->users()->where('user_id', $data['user_id'])->exists();
        if (! $isMember) {
            return response()->json(['message' => 'El usuario no es miembro de esta empresa'], 422);
        }

        // Evitar duplicados
        $exists = OrgAreaUserRole::where([
            'user_id' => $data['user_id'],
            'org_area_id' => $data['org_area_id'],
            'org_role_id' => $data['org_role_id'],
        ])->exists();

        if ($exists) {
            return response()->json(['message' => 'Esta asignación ya existe'], 409);
        }

        $assignment = OrgAreaUserRole::create($data);

        return response()->json($assignment->load(['user.profile', 'area', 'position']), 201);
    }

    /**
     * Eliminar asignación
     */
    public function destroy(string $uid, $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);
        $this->authorize('manage_areas');

        $assignment = OrgAreaUserRole::where('id', $id)
            ->whereHas('area', function ($q) use ($company) {
                $q->where('org_company_id', $company->id);
            })->firstOrFail();

        $assignment->delete();

        return response()->json(['message' => 'Asignación eliminada']);
    }

    /**
     * Listar equipo por Área (Ya lo tenías, mantenido por compatibilidad)
     */
    public function byArea(string $uid, string $areaUid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);
        $this->authorize('view_areas');

        $area = OrgArea::where('uid', $areaUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        $team = OrgAreaUserRole::where('org_area_id', $area->id)
            ->with(['user.profile', 'position'])
            ->orderByDesc('is_primary')
            ->get();

        return response()->json($team);
    }
}
