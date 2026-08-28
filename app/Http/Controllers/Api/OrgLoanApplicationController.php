<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Mail\LoanStatusUpdatedMail;
use App\Models\OrgCompany;
use App\Models\OrgLoanApplication;
use App\Traits\TriggersModuleAutomations;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OrgLoanApplicationController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace, TriggersModuleAutomations;

    /**
     * 📋 Listar todas las solicitudes de préstamos de la compañía (Para el Admin)
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Seguridad Contextual
            $this->authorizeWorkspace($company);
            $this->authorize('view_loan'); // Asegúrate de tener este permiso en Spatie

            // Listar solicitudes ordenadas por las más recientes y cargando al cliente
            $applications = OrgLoanApplication::with(['customer', 'user'])
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
            $this->authorize('view_loan');

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
            $this->authorize('manage_loan'); // Permiso para administrar préstamos

            //  2. Cargamos al partner ('user') para poder enviarle el correo
            $application = OrgLoanApplication::with(['customer', 'user'])
                ->where('uid', $applicationUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            //  3. Guardamos el estatus viejo ANTES de actualizar
            $oldStatus = $application->status;

            $request->validate([
                'status' => 'sometimes|required|in:Open,Lost,Won,Abandon',
                'loan_type' => 'sometimes|string|max:100',
                'commission_amount' => 'sometimes|numeric|min:0',
                'commission_status' => 'sometimes|in:pending,paid,not_applicable',
                'seller_payout_date' => 'nullable|date',
            ]);

            $application->update($request->all());

            $this->triggerAutomations($company, 'loans', 'updated', $application);

            // 👈 4. REGLA DE NEGOCIO: Notificar al Partner si el estatus cambió
            if ($request->has('status') && $oldStatus !== $request->status) {
                // Si la solicitud tiene un partner asignado (user) y su correo existe
                if ($application->user && $application->user->email) {

                    // Usamos ->queue() para que se envíe asincrónicamente y no demore la petición
                    Mail::to($application->user->email)->queue(new LoanStatusUpdatedMail($application, $company));
                }
            }

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
            $this->authorize('manage_loan');

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
