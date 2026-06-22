<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgInsuranceApplication; // Asegúrate de tener este modelo
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgInsuranceApplicationController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📋 Listar todas las solicitudes de seguros de la compañía
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Seguridad Contextual
            $this->authorizeWorkspace($company);
            $this->authorize('view_insurance');

            // Listar solicitudes ordenadas por las más recientes
            $applications = OrgInsuranceApplication::where('org_company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['data' => $applications], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar las solicitudes de seguros.'], 500);
        }
    }

    /**
     * 👁️ Ver el detalle de una solicitud específica
     */
    public function show(string $uid, string $applicationUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            
            $this->authorizeWorkspace($company);
            $this->authorize('view_insurance');

            $application = OrgInsuranceApplication::where('uid', $applicationUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json(['data' => $application], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener la solicitud o no existe.'], 404);
        }
    }

    /**
     * ✏️ Actualizar una solicitud (Ej: cambiar el estatus a "approved" o "rejected")
     */
    public function update(Request $request, string $uid, string $applicationUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            
            $this->authorizeWorkspace($company);
            $this->authorize('manage_insurance');

            $application = OrgInsuranceApplication::where('uid', $applicationUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $request->validate([
                'status' => 'sometimes|required|in:pending,reviewing,approved,rejected,completed',
                'insurance_type' => 'sometimes|string|max:255',
                // Puedes agregar más campos que el administrador pueda editar
            ]);

            $application->update($request->all());

            return response()->json([
                'message' => 'Solicitud de seguro actualizada correctamente.',
                'data' => $application,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la solicitud.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar una solicitud
     */
    public function destroy(string $uid, string $applicationUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            
            $this->authorizeWorkspace($company);
            $this->authorize('manage_insurance');

            $application = OrgInsuranceApplication::where('uid', $applicationUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $application->delete();

            return response()->json(['message' => 'Solicitud eliminada correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la solicitud.'], 500);
        }
    }
}