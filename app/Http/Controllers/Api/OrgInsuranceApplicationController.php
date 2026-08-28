<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Mail\InsuranceStatusUpdatedMail;
use App\Models\OrgCompany;
use App\Models\OrgInsuranceApplication;
use App\Traits\TriggersModuleAutomations;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OrgInsuranceApplicationController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace,TriggersModuleAutomations;

    /**
     * 📋 Listar todas las solicitudes de seguros de la compañía (Para el Admin)
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Seguridad Contextual
            $this->authorizeWorkspace($company);
            $this->authorize('view_insurance');

            // Listar solicitudes ordenadas por las más recientes y cargando al cliente
            $applications = OrgInsuranceApplication::with(['customer', 'user'])
                ->where('org_company_id', $company->id)
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

            $application = OrgInsuranceApplication::with('customer')
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
            $this->authorize('manage_insurance');

            $application = OrgInsuranceApplication::with(['customer', 'user'])
                ->where('uid', $applicationUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $oldStatus = $application->status;

            $request->validate([
                'status' => 'sometimes|required|in:Open,Lost,Won,Abandon',
                'insurance_type' => 'sometimes|string|max:255',
                'commission_amount' => 'sometimes|numeric|min:0',
                'commission_status' => 'sometimes|in:pending,paid,not_applicable',
                'seller_payout_date' => 'nullable|date',
            ]);

            $application->update($request->all());

            $this->triggerAutomations($company, 'insurances', 'updated', $application);

            //  5. REGLA DE NEGOCIO: Notificar al Partner si el estatus cambió
            if ($request->has('status') && $oldStatus !== $request->status) {
                if ($application->user && $application->user->email) {
                    Mail::to($application->user->email)->queue(new InsuranceStatusUpdatedMail($application, $company));
                }
            }

            return response()->json([
                'message' => 'Solicitud de seguro actualizada correctamente.',
                'data' => $application,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la solicitud de seguro.'], 500);
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

            return response()->json(['message' => 'Solicitud de seguro eliminada correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la solicitud.'], 500);
        }
    }
}
