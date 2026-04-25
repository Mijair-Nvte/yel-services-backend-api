<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgArea;
use App\Models\OrgCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrgAreaController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 🧩 Listar áreas de la compañía
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_areas');

            return response()->json(
                $company->areas()->orderBy('name')->get()
            );
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar áreas.'], 500);
        }
    }

    /**
     * 📝 Crear área
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_areas');

            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $area = OrgArea::create([
                ...$data,
                'uid' => 'are_'.Str::ulid(),
                'slug' => Str::slug($data['name']),
                'org_company_id' => $company->id,
            ]);

            return response()->json($area, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear área.'], 500);
        }
    }

    /**
     * 🔍 Ver detalle (Normalizado)
     */
    public function show(string $uid, string $areaUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_areas');

            $area = OrgArea::where('uid', $areaUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json($area);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Área no encontrada.'], 404);
        }
    }

    /**
     * ✏️ Actualizar (Normalizado)
     */
    public function update(Request $request, string $uid, string $areaUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_areas');

            $area = OrgArea::where('uid', $areaUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $data = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            if (isset($data['name'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $area->update($data);

            return response()->json($area);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar área.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar (Normalizado)
     */
    public function destroy(string $uid, string $areaUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_areas');

            $area = OrgArea::where('uid', $areaUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $area->delete();

            return response()->json(['message' => 'Área eliminada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar área.'], 500);
        }
    }
}
