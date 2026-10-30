<?php

namespace App\Http\Controllers\Api;

use App\Exports\EventRegistrationsExport;
use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use App\Models\OrgEventRegistration;
use App\Traits\HandlesCustomers;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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

    /**
     * 📞 Actualizar el estado de contacto desde el panel del Call Center
     */
    public function updateContactStatus(Request $request, string $uid, string $eventUid, string $registrationUid)
    {
        $validated = $request->validate([
            'contact_status' => 'required|string|max:50',
            'notes' => 'nullable|string', // Opcional: aprovechar para actualizar notas del lead
        ]);

        // Opcional pero recomendado: Validar seguridad igual que en los otros métodos
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);
        $this->authorize('manage_calendar');

        // Buscar directamente el registro
        $registration = OrgEventRegistration::where('uid', $registrationUid)->firstOrFail();

        // Actualizamos el estado y agregamos notas si vienen en la petición (ej. "Llamar en 1 semana")
        $registration->update([
            'contact_status' => $validated['contact_status'],
            'notes' => $request->filled('notes')
                ? $registration->notes."\n[".now()->format('Y-m-d H:i').'] '.$validated['notes']
                : $registration->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estado de seguimiento actualizado correctamente.',
            'data' => [
                'uid' => $registration->uid,
                'contact_status' => $registration->contact_status,
                'notes' => $registration->notes,
            ],
        ]);
    }

    /**
     * Exportar los registros (asistentes) a PDF
     */
    public function exportPdf(Request $request, string $uid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $request->validate([
                'registration_ids' => 'required|array',
                'registration_ids.*' => 'integer',
            ]);

            $registrations = OrgEventRegistration::with(['customer'])
                ->where('org_company_id', $company->id)
                ->where('org_event_id', $event->id)
                ->whereIn('id', $request->registration_ids)
                ->orderBy('created_at', 'desc')
                ->get();

            $logoPath = public_path('assets/img/logo-reportes.png');
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                $data = file_get_contents($logoPath);
                $logoBase64 = 'data:image/'.$type.';base64,'.base64_encode($data);
            }

            // Nota: Debes crear una vista blade 'resources/views/pdf/event-registrations-report.blade.php'
            // similar a la de ventas pero iterando sobre $registrations.
            $pdf = Pdf::loadView('pdf.event-registrations-report', [
                'event' => $event,
                'registrations' => $registrations,
                'company' => $company,
                'fechaReporte' => now()->format('d/m/Y H:i'),
                'logoBase64' => $logoBase64,
            ]);

            return $pdf->download('reporte_asistentes.pdf');

        } catch (\Exception $e) {
            \Log::error('Error PDF Asistentes', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Error al generar el PDF.'], 500);
        }
    }

    /**
     * Exportar los registros (asistentes) a Excel
     */
    public function exportExcel(Request $request, string $uid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $request->validate([
                'registration_ids' => 'required|array',
                'registration_ids.*' => 'integer',
            ]);

            $registrations = OrgEventRegistration::with(['customer'])
                ->where('org_company_id', $company->id)
                ->where('org_event_id', $event->id)
                ->whereIn('id', $request->registration_ids)
                ->orderBy('created_at', 'desc')
                ->get();

            return Excel::download(new EventRegistrationsExport($registrations, $event), 'reporte_asistentes.xlsx');

        } catch (\Exception $e) {
            \Log::error('Error Excel Asistentes', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Error al generar el Excel.'], 500);
        }
    }
}
