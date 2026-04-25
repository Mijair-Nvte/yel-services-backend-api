<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgPaymentLinkMapping;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgPaymentLinkMappingController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 🔗 Listar todos los mapeos de la compañía
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Seguridad Contextual
            $this->authorizeWorkspace($company);
            $this->authorize('view_payment_links');

            $mappings = OrgPaymentLinkMapping::with('seller:id,name,email')
                ->where('org_company_id', $company->id)
                ->orderBy('service_name', 'asc')
                ->get();

            return response()->json(['data' => $mappings], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar mapeos.'], 500);
        }
    }

    /**
     * ➕ Crear un nuevo mapeo
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_payment_links');

            $request->validate([
                'seller_id' => 'required|exists:users,id',
                'ghl_payment_link_id' => 'required|string',
                'service_name' => 'required|string|max:255',
                'is_active' => 'boolean',
            ]);

            // Validar unicidad dentro de la misma empresa
            $exists = OrgPaymentLinkMapping::where('org_company_id', $company->id)
                ->where('ghl_payment_link_id', $request->ghl_payment_link_id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Este ID de Link ya está asignado en tu empresa.'], 422);
            }

            $mapping = OrgPaymentLinkMapping::create([
                'org_company_id' => $company->id,
                'seller_id' => $request->seller_id,
                'ghl_payment_link_id' => $request->ghl_payment_link_id,
                'service_name' => $request->service_name,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'message' => 'Mapeo creado correctamente.',
                'data' => $mapping->load('seller:id,name,email'),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el mapeo.'], 500);
        }
    }

    /**
     * ✏️ Actualizar mapeo
     */
    public function update(Request $request, string $uid, string $mappingUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_payment_links');

            $mapping = OrgPaymentLinkMapping::where('uid', $mappingUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $request->validate([
                'seller_id' => 'sometimes|required|exists:users,id',
                'ghl_payment_link_id' => 'sometimes|required|string',
                'service_name' => 'sometimes|required|string|max:255',
                'is_active' => 'boolean',
            ]);

            $mapping->update($request->all());

            return response()->json([
                'message' => 'Mapeo actualizado correctamente.',
                'data' => $mapping->load('seller:id,name,email'),
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el mapeo.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar mapeo
     */
    public function destroy(string $uid, string $mappingUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_payment_links');

            $mapping = OrgPaymentLinkMapping::where('uid', $mappingUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $mapping->delete();

            return response()->json(['message' => 'Mapeo eliminado correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el mapeo.'], 500);
        }
    }
}
