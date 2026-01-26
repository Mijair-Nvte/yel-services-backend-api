<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgArea;
use App\Models\OrgAreaUserRole;
use Illuminate\Http\Request;

class OrgAreaUserRoleController extends Controller
{
    use AuthorizesWorkspace;

    /**
     * Listado general (filtrable)
     */
    public function index(Request $request)
    {
        $query = OrgAreaUserRole::with([
            'user.profile',
            'area',
            'position',
        ]);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('org_area_id')) {
            $query->where('org_area_id', $request->org_area_id);
        }

        return response()->json(
            $query->get()
        );
    }

    /**
     * Crear asignación usuario ↔ departamento ↔ puesto
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'org_area_id' => 'required|exists:org_areas,id',
            'org_role_id' => 'required|exists:org_positions,id',
            'position_title' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $assignment = OrgAreaUserRole::create($data);

        return response()->json(
            $assignment->load(['user.profile', 'area', 'position']),
            201
        );
    }

    /**
     * Ver detalle de una asignación
     */
    public function show($id)
    {
        return response()->json(
            OrgAreaUserRole::with([
                'user.profile',
                'area',
                'position',
            ])->findOrFail($id)
        );
    }

    /**
     * Actualizar flags
     */
    public function update(Request $request, $id)
    {
        $assignment = OrgAreaUserRole::findOrFail($id);

        $data = $request->validate([
            'position_title' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $assignment->update($data);

        return response()->json(
            $assignment->load(['user.profile', 'area', 'position'])
        );
    }

    /**
     * Eliminar asignación
     */
    public function destroy($id)
    {
        OrgAreaUserRole::findOrFail($id)->delete();

        return response()->json(['message' => 'Assignment deleted']);
    }

    /**
     * Equipo de un departamento (CLAVE)
     */
    public function byArea(string $uid)
    {
        $area = OrgArea::where('uid', $uid)
            ->with('company')
            ->firstOrFail();

        $this->authorizeWorkspace($area->company);

        return response()->json(
            OrgAreaUserRole::where('org_area_id', $area->id)
                ->with(['user.profile', 'position'])
                ->orderByDesc('is_primary')
                ->get()
        );
    }
}
