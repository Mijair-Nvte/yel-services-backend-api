<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use App\Models\OrgEventRegistration;
use App\Traits\HandlesCustomers;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgEventRegistrationController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace,HandlesCustomers;

    /**
     * 📥 Importar asistentes masivamente desde un JSON (pre-procesado en Frontend)
     */
    public function import(Request $request, string $uid, string $eventUid)
    {
        try {
            // 1. Validar la compañía y permisos
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            // 2. Validar el evento
            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            // 3. Recibir los contactos (El frontend enviará un arreglo estructurado tras el mapeo)
            $contacts = $request->input('contacts', []);

            if (empty($contacts) || ! is_array($contacts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionaron contactos válidos para importar.',
                ], 400);
            }

            $importedCount = 0;
            $skippedCount = 0;

            // 4. Iterar sobre cada contacto
            foreach ($contacts as $contact) {
                // Datos básicos
                $fullName = trim($contact['name'] ?? 'Cliente Desconocido');
                $email = trim($contact['email'] ?? null);
                $phone = trim($contact['phone'] ?? null);
                $source = trim($contact['source'] ?? 'Importación CSV');

                // 📌 DATO DE ESTADO (Geográfico - California, Texas, etc.)
                // Como bien mencionas, esto es del Customer. Te lo dejo extraído para que
                // si actualizas tu Trait HandlesCustomers, se lo puedas pasar fácilmente.
                $stateLocation = trim($contact['state'] ?? null);

                // ✅ PROCESAR ASISTENCIA (Checkboxes de Excel)
                $rawAttended = $contact['attended'] ?? null;
                $isAttended = false;

                if ($rawAttended !== null) {
                    $val = strtolower(trim((string) $rawAttended));
                    // Evaluamos cualquier variante de string positivo (true, 1, yes, si, on, x, v, etc.)
                    $isAttended = in_array($val, ['1', 'true', 'si', 'sí', 'yes', 'on', 'checked', 'v', 'verdadero', 'x']);
                }

                // Validar que al menos tenga un dato útil de contacto
                if (empty($email) && empty($phone) && $fullName === 'Cliente Desconocido') {
                    $skippedCount++;

                    continue; // Nos saltamos filas vacías o basura
                }

                // 🔥 PASO MAGICO: Usamos tu Trait para encontrar o crear al cliente
                // Nota: Si agregas la columna state a tu BD de clientes, debes modificar
                // este trait para recibir $stateLocation
                $customerId = $this->findOrCreateCustomer(
                    $company->id,
                    $fullName,
                    $email,
                    $phone
                );

                // 🚦 VALIDACIÓN DE DUPLICIDAD EN EL EVENTO
                $alreadyRegistered = OrgEventRegistration::where('org_event_id', $event->id)
                    ->where('org_customer_id', $customerId)
                    ->exists();

                if ($alreadyRegistered) {
                    $skippedCount++;

                    continue;
                }

                // ✅ REGISTRO EN EL EVENTO
                OrgEventRegistration::create([
                    'org_company_id' => $company->id,
                    'org_event_id' => $event->id,
                    'org_customer_id' => $customerId,
                    'attended' => $isAttended, // Guardamos el booleano extraído
                    'status' => $isAttended ? 'attended' : 'registered', // Ajustamos el string para el frontend
                    'source' => $source,
                    'registered_at' => now(),
                    'is_new_lead' => false,
                ]);

                $importedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Importación completada. $importedCount agregados, $skippedCount omitidos.",
                'data' => [
                    'imported' => $importedCount,
                    'skipped' => $skippedCount,
                    'total' => count($contacts),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('🔥 ERROR AL IMPORTAR ASISTENTES: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la importación.',
            ], 500);
        }
    }

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
                'message' => 'El registro ha sido eliminado permanentemente.',
            ]);

        } catch (\Exception $e) {
            \Log::error('🔥 ERROR AL ELIMINAR REGISTRO DE EVENTO: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el registro del evento.',
            ], 500);
        }
    }
}
