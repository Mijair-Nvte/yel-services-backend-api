<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgPaymentLinkMapping;
use Illuminate\Http\Request;

class OrgPaymentLinkMappingController extends Controller
{
    /**
     * Listar todos los mapeos de la compañía
     */
    public function index($uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // Traemos el mapeo con la relación del vendedor (User)
        $mappings = OrgPaymentLinkMapping::with('seller:id,name,email')
            ->where('org_company_id', $company->id)
            ->orderBy('service_name', 'asc')
            ->get();

        return response()->json(['data' => $mappings], 200);
    }

    /**
     * Crear un nuevo mapeo
     */
    public function store(Request $request, $uid)
    {
        $request->validate([
            'seller_id'           => 'required|exists:users,id',
            'ghl_payment_link_id' => 'required|string',
            'service_name'        => 'required|string|max:255',
            'is_active'           => 'boolean'
        ]);

        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // Validar que el link no esté ya registrado en esta empresa
        $exists = OrgPaymentLinkMapping::where('org_company_id', $company->id)
            ->where('ghl_payment_link_id', $request->ghl_payment_link_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Este ID de Link ya está asignado en tu empresa.'], 422);
        }

        $mapping = OrgPaymentLinkMapping::create([
            'org_company_id'      => $company->id,
            'seller_id'           => $request->seller_id,
            'ghl_payment_link_id' => $request->ghl_payment_link_id,
            'service_name'        => $request->service_name,
            'is_active'           => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Mapeo creado correctamente.',
            'data'    => $mapping->load('seller:id,name,email')
        ], 201);
    }

    /**
     * Actualizar un mapeo existente
     */
    public function update(Request $request, $uid, $mappingUid)
    {
        $request->validate([
            'seller_id'           => 'sometimes|required|exists:users,id',
            'ghl_payment_link_id' => 'sometimes|required|string',
            'service_name'        => 'sometimes|required|string|max:255',
            'is_active'           => 'boolean'
        ]);

        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $mapping = OrgPaymentLinkMapping::where('uid', $mappingUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        $mapping->update($request->all());

        return response()->json([
            'message' => 'Mapeo actualizado correctamente.',
            'data'    => $mapping->load('seller:id,name,email')
        ], 200);
    }

    /**
     * Eliminar un mapeo
     */
    public function destroy($uid, $mappingUid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $mapping = OrgPaymentLinkMapping::where('uid', $mappingUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        $mapping->delete();

        return response()->json(['message' => 'Mapeo eliminado correctamente.'], 200);
    }
}