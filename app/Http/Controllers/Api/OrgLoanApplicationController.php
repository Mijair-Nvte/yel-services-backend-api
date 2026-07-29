<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgLoanApplication;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgLoanApplicationController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📋 Listar todas las solicitudes de préstamos de la compañía (Para el Admin)
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Seguridad Contextual
            $this->authorizeWorkspace($company);
            $this->authorize('view_loans'); // Asegúrate de tener este permiso en Spatie

            // Listar solicitudes ordenadas por las más recientes y cargando al cliente
            $applications = OrgLoanApplication::with('customer')
                ->where('org_company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['data' => $applications], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar las solicitudes de préstamos.'], 500);
        }
    }

    /**
     * 👁️ Ver el detalle de una solicitud de préstamo específica
     */
    public function show(string $uid, string $applicationUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('view_loans');

            $application = OrgLoanApplication::with('customer')
                ->where('uid', $applicationUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json(['data' => $application], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener la solicitud o no existe.'], 404);
        }
    }

    /**
     * ✏️ Actualizar una solicitud (Estatus, Comisiones, etc.)
     */
    public function update(Request $request, string $uid, string $applicationUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_loans'); // Permiso para administrar préstamos

            $application = OrgLoanApplication::where('uid', $applicationUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $request->validate([
                'status' => 'sometimes|required|in:pending,reviewing,approved,rejected,completed',
                'loan_type' => 'sometimes|string|max:100',
                'commission_amount' => 'sometimes|numeric|min:0',
                'commission_status' => 'sometimes|in:pending,paid,not_applicable',
                'seller_payout_date' => 'nullable|date',
            ]);

            $application->update($request->all());

            return response()->json([
                'message' => 'Solicitud de préstamo actualizada correctamente.',
                'data' => $application,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la solicitud de préstamo.'], 500);
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
            $this->authorize('manage_loans');

            $application = OrgLoanApplication::where('uid', $applicationUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $application->delete();

            return response()->json(['message' => 'Solicitud de préstamo eliminada correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la solicitud.'], 500);
        }
    }
}
