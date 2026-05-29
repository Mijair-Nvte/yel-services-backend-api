<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgInvestorTier;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgInvestorTierController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('view_investors');

            $tiers = OrgInvestorTier::where('org_company_id', $company->id)
                ->orderBy('min_properties', 'asc')
                ->get();

            return response()->json(['data' => $tiers], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar los niveles de inversionistas.'], 500);
        }
    }

    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            
            $this->authorizeWorkspace($company);
            $this->authorize('manage_investors');

            $request->validate([
                'name' => 'required|string|max:255',
                'min_properties' => 'required|integer|min:0',
                'max_properties' => 'nullable|integer|gt:min_properties',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'features' => 'nullable|array',
                'color_theme' => 'nullable|string|max:50',
                'is_active' => 'boolean'
            ]);

            $tier = OrgInvestorTier::create([
                'org_company_id' => $company->id,
                'name' => $request->name,
                'min_properties' => $request->min_properties,
                'max_properties' => $request->max_properties,
                'discount_percentage' => $request->discount_percentage ?? 0.00,
                'features' => $request->features,
                'color_theme' => $request->color_theme,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'message' => 'Nivel creado correctamente.',
                'data' => $tier
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el nivel.'], 500);
        }
    }

    public function update(Request $request, string $uid, string $tierUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            
            $this->authorizeWorkspace($company);
            $this->authorize('manage_investors');

            $tier = OrgInvestorTier::where('org_company_id', $company->id)
                ->where('uid', $tierUid)
                ->firstOrFail();

            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'min_properties' => 'sometimes|required|integer|min:0',
                'max_properties' => 'nullable|integer|gt:min_properties',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'features' => 'nullable|array',
                'color_theme' => 'nullable|string|max:50',
                'is_active' => 'boolean'
            ]);

            $tier->update($request->all());

            return response()->json([
                'message' => 'Nivel actualizado correctamente.',
                'data' => $tier
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el nivel.'], 500);
        }
    }

    public function destroy(string $uid, string $tierUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            
            $this->authorizeWorkspace($company);
            $this->authorize('manage_investors');

            $tier = OrgInvestorTier::where('org_company_id', $company->id)
                ->where('uid', $tierUid)
                ->firstOrFail();

            $tier->delete();

            return response()->json(['message' => 'Nivel eliminado correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el nivel.'], 500);
        }
    }
}