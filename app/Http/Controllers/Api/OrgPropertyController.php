<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgProperty;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgPropertyController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('view_investors');

            $properties = OrgProperty::with('owner:id,name,email')
                ->where('org_company_id', $company->id)
                ->latest()
                ->get();

            return response()->json(['data' => $properties], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar las propiedades.'], 500);
        }
    }

    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_investors');

            $request->validate([
                'user_id' => 'required|exists:users,id',
                'title' => 'required|string|max:255',
                'portfolio_type' => 'nullable|string|max:255',
                'investment_amount' => 'nullable|numeric|min:0',
                'cash_flow_status' => 'nullable|string|max:255',
                'image_path' => 'nullable|string',
                'status' => 'required|in:prospect,in_progress,closed',
                'closed_at' => 'nullable|date',
            ]);

            // Auto-asignar fecha si viene cerrada y sin fecha
            $closedAt = $request->closed_at;
            if ($request->status === 'closed' && empty($closedAt)) {
                $closedAt = now();
            }

            $property = OrgProperty::create([
                'org_company_id' => $company->id,
                'user_id' => $request->user_id,
                'title' => $request->title,
                'portfolio_type' => $request->portfolio_type,
                'investment_amount' => $request->investment_amount ?? 0.00,
                'cash_flow_status' => $request->cash_flow_status,
                'image_path' => $request->image_path,
                'status' => $request->status,
                'closed_at' => $closedAt,
            ]);

            return response()->json([
                'message' => 'Propiedad agregada correctamente.',
                'data' => $property->load('owner:id,name,email'),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear la propiedad.'], 500);
        }
    }

    public function update(Request $request, string $uid, string $propertyUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_investors');

            $property = OrgProperty::where('org_company_id', $company->id)
                ->where('uid', $propertyUid)
                ->firstOrFail();

            $request->validate([
                'user_id' => 'sometimes|required|exists:users,id',
                'title' => 'sometimes|required|string|max:255',
                'portfolio_type' => 'nullable|string|max:255',
                'investment_amount' => 'nullable|numeric|min:0',
                'cash_flow_status' => 'nullable|string|max:255',
                'image_path' => 'nullable|string',
                'status' => 'sometimes|required|in:prospect,in_progress,closed',
                'closed_at' => 'nullable|date',
            ]);

            $data = $request->all();

            // Si el estado cambia a closed y no traía fecha
            if (isset($data['status']) && $data['status'] === 'closed' && empty($property->closed_at) && empty($data['closed_at'])) {
                $data['closed_at'] = now();
            }

            $property->update($data);

            return response()->json([
                'message' => 'Propiedad actualizada correctamente.',
                'data' => $property->load('owner:id,name,email'),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la propiedad.'], 500);
        }
    }

    public function destroy(string $uid, string $propertyUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_investors');

            $property = OrgProperty::where('org_company_id', $company->id)
                ->where('uid', $propertyUid)
                ->firstOrFail();

            $property->delete();

            return response()->json(['message' => 'Propiedad eliminada correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la propiedad.'], 500);
        }
    }
}
