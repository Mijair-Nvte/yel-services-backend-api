<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use App\Models\OrgEventRegistration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgEventRegistrationController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 🗑️ Eliminar permanentemente un registro de evento (Hard Delete)
     */
    public function destroy(string $uid, string $eventUid, string $registrationUid)
    {
        try {
            // 1. Validar la compañía y permisos
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            // 2. Validar que el evento exista y pertenezca a la compañía
            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            // 3. Validar que el registro exista y pertenezca a este evento
            $registration = OrgEventRegistration::where('uid', $registrationUid)
                ->where('org_event_id', $event->id)
                ->firstOrFail();

            // 4. Eliminación física (Hard Delete)
            $registration->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'El registro ha sido eliminado permanentemente.'
            ]);

        } catch (\Exception $e) {
            \Log::error('🔥 ERROR AL ELIMINAR REGISTRO DE EVENTO: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el registro del evento.'
            ], 500);
        }
    }
}