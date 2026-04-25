<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyLink;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgCompanyLinkController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    // 📌 Listar links de una compañía
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_company_links');

            return response()->json([
                'data' => $company->links()->latest()->get(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener links.'], 500);
        }
    }

    // 📌 Crear link
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_company_links');

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'url' => 'required|url|max:500',
                'description' => 'nullable|string',
            ]);

            $link = $company->links()->create($validated);

            return response()->json(['message' => 'Link creado', 'data' => $link], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear link.'], 500);
        }
    }

    // 📌 Mostrar un link (Normalizado)
    public function show(string $uid, string $linkUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_company_links');

            $link = OrgCompanyLink::where('uid', $linkUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json(['data' => $link], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Link no encontrado.'], 404);
        }
    }

    // 📌 Actualizar link (Normalizado)
    public function update(Request $request, string $uid, string $linkUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_company_links');

            $link = OrgCompanyLink::where('uid', $linkUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'url' => 'sometimes|required|url|max:500',
                'description' => 'nullable|string',
            ]);

            $link->update($validated);

            return response()->json(['message' => 'Link actualizado', 'data' => $link], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar.'], 500);
        }
    }

    // 📌 Eliminar link (Normalizado)
    public function destroy(string $uid, string $linkUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_company_links');

            $link = OrgCompanyLink::where('uid', $linkUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $link->delete();

            return response()->json(['message' => 'Link eliminado'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar.'], 500);
        }
    }
}
