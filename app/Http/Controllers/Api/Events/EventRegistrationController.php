<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Jobs\GoHighLevel\Events\SendEventRegistrationToGHL;
use App\Models\OrgCompany;
use App\Models\OrgCustomer;
use App\Models\OrgEvent;
use App\Models\OrgEventRegistration;
use App\Traits\HandlesCustomers;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{
    use HandlesCustomers;

    public function store(Request $request, $companyUid, $slug)
    {
        // 1. Validar los datos de entrada del formulario
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // 2. Resolver compañía y evento mediante el slug
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $event = OrgEvent::where('org_company_id', $company->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // 3. Unimos nombre y apellido para pasárselo al trait
        $fullName = trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? ''));

        // Usamos tu trait para buscar o crear el cliente y obtener su ID
        $customerId = $this->findOrCreateCustomer(
            companyId: $company->id,
            fullName: $fullName,
            email: $validated['email'],
            phone: $validated['phone'] ?? null
        );

        // Opcional: Si necesitas guardar los UTMs en el metadata del cliente recién encontrado/creado
        $customer = OrgCustomer::find($customerId);
        if ($customer && ($request->input('utm_source') || $request->input('utm_medium') || $request->input('utm_campaign'))) {
            $currentMetadata = $customer->metadata ?? [];
            $customer->update([
                'metadata' => array_merge($currentMetadata, [
                    'utm_source' => $request->input('utm_source'),
                    'utm_medium' => $request->input('utm_medium'),
                    'utm_campaign' => $request->input('utm_campaign'),
                ]),
            ]);
        }

        // 4. Registrar la asistencia al evento (o recuperar la existente)
        $registration = OrgEventRegistration::firstOrCreate(
            [
                'org_event_id' => $event->id,
                'org_customer_id' => $customerId,
            ],
            [
                'org_company_id' => $company->id,
                'registered_at' => Carbon::now(),
                'is_new_lead' => $customer->wasRecentlyCreated ?? false,
                'ticket_quantity' => 1,
                'source' => $validated['source'] ?? 'Directo',
                'notes' => $validated['notes'] ?? null,
            ]
        );

        // 👇 VALIDACIÓN PARA EVITAR DUPLICADOS EN GHL 👇
        if (! $registration->wasRecentlyCreated) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico ya se encuentra registrado para este evento.',
                'data' => [
                    'registration_uid' => $registration->uid,
                    'event_title' => $event->title,
                ],
            ], 409); // 409 Conflict: El recurso ya existe
        }

        // ======================================================
        // 5. ENVIAR DATOS A GOHIGHLEVEL MEDIANTE JOB (COLA)
        // ======================================================

        // Crear tag único: slug + fecha (ej: comprar-casa-llc-2026-09-24)
        // Cambia esta línea en tu EventRegistrationController:
        $ghlTag = $event->event_tag ?? 'event-'.Carbon::parse($event->starts_at)->format('Ymd');

        $eventsDomain = config('app.events_frontend_url', 'https://eventos.yaestoylisto.com');
        $magicLink = "{$eventsDomain}/access/{$registration->uid}";

        $ghlPayload = [
            'event' => 'created',
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? '',
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? '',
            'tags' => [$ghlTag],
            'source' => $validated['source'] ?? 'Web Organica',
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'event_title' => $event->title,
            'event_date' => $event->starts_at,
            'company_uid' => $company->uid,
            'event_uid' => $event->uid,
            'registration_uid' => $registration->uid,
            'access_link' => $magicLink,
        ];

        // Despachamos el Job para que corra en segundo plano
        SendEventRegistrationToGHL::dispatch($ghlPayload);

        return response()->json([
            'success' => true,
            'message' => '¡Te has registrado exitosamente al evento!',
            'data' => [
                'registration_uid' => $registration->uid,
                'event_title' => $event->title,
                'customer_email' => $customer->email ?? $validated['email'],
            ],
        ], 201);
    }

    /**
     * 🚪 Validar acceso por Enlace Mágico (Magic Link), marcar asistencia y redirigir al Meet
     */
    public function access(Request $request, string $registrationUid)
    {
        try {
            \Log::info('========== INTENTO DE ACCESO A EVENTO ==========');
            \Log::info("Token de registro recibido: {$registrationUid}");

            // 1. Buscamos el registro y cargamos su evento y cliente asociado
            $registration = OrgEventRegistration::with(['event', 'customer'])
                ->where('uid', $registrationUid)
                ->first();

            if (! $registration) {
                \Log::warning("⚠️ Intento de acceso fallido: El token {$registrationUid} no existe.");

                return response()->json([
                    'success' => false,
                    'message' => 'Enlace de acceso inválido o expirado.',
                ], 404);
            }

            $event = $registration->event;
            $customer = $registration->customer;

            if (! $event) {
                return response()->json([
                    'message' => 'El evento asociado ya no está disponible.',
                ], 404);
            }

            // 2. Marcar como asistente si aún no lo ha hecho
            if (! $registration->attended) {
                $registration->update([
                    'attended' => true,
                ]);
                \Log::info("✅ Asistencia marcada para el cliente: {$customer->email} en el evento: {$event->title}");

                // ======================================================
                // 🚀 ENVIAR DATOS A GOHIGHLEVEL (UPDATED / ASISTENCIA)
                // ======================================================
                $company = OrgCompany::find($registration->org_company_id);
                $eventsDomain = config('app.events_frontend_url', 'https://eventos.yaestoylisto.com');
                $magicLink = "{$eventsDomain}/api/v1/events/access/{$registration->uid}";

                $ghlTag = $event->event_tag ?? 'event-'.Carbon::parse($event->starts_at)->format('Ymd');

                $ghlPayload = [
                    'event' => 'updated',
                    'first_name' => $customer->first_name ?? '',
                    'last_name' => $customer->last_name ?? '',
                    'email' => $customer->email,
                    'phone' => $customer->phone ?? '',
                    'tags' => [$ghlTag, 'Asistió al Evento'], // Puedes agregar un tag extra de asistencia si gustas
                    'source' => $registration->source ?? 'Web Organica',
                    'event_title' => $event->title,
                    'event_date' => $event->starts_at,
                    'company_uid' => $company->uid,
                    'event_uid' => $event->uid,
                    'registration_uid' => $registration->uid,
                    'access_link' => $magicLink,
                ];

                SendEventRegistrationToGHL::dispatch($ghlPayload);

            } else {
                \Log::info("ℹ️ El usuario {$customer->email} ya tenía su asistencia marcada previamente.");
            }

            // 3. Obtener la URL de la reunión (meeting_url del evento)
            $event->makeVisible(['meeting_url']);
            $meetingUrl = $event->meeting_url;

            if (empty($meetingUrl)) {
                // Si no hay Meet configurado, redirigimos al dominio correcto de eventos usando su slug
                $eventsDomain = config('app.events_frontend_url', 'https://eventos.yaestoylisto.com');
                $meetingUrl = "{$eventsDomain}/evento/{$event->slug}";
            }

            \Log::info("🚀 Redirigiendo al usuario a la URL: {$meetingUrl}");
            \Log::info('========== FIN ACCESO A EVENTO ==========');

            // 4. Redirección HTTP real al Google Meet o Sala en Vivo
            return redirect()->away($meetingUrl);

        } catch (\Exception $e) {
            \Log::error('🔥 ERROR CRÍTICO AL ACCEDER AL EVENTO: '.$e->getMessage().' en línea '.$e->getLine());

            return response()->json([
                'message' => 'Error al procesar tu acceso al evento.',
            ], 500);
        }
    }

    /**
     * 🎟️ Listar los eventos en los que el usuario autenticado está inscrito (para la App / Portales)
     */
    public function myRegistrations(Request $request, $companyUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $user = auth()->user();

            // Buscamos si el usuario autenticado tiene un registro como customer en esta compañía
            $customer = OrgCustomer::where('org_company_id', $company->id)
                ->where('email', $user->email)
                ->first();

            if (! $customer) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            // Obtenemos los eventos a través de las inscripciones del cliente
            $registrations = OrgEventRegistration::with(['event'])
                ->where('org_company_id', $company->id)
                ->where('org_customer_id', $customer->id)
                ->latest()
                ->get();

            $events = $registrations->map(function ($reg) {
                $event = $reg->event;
                if (! $event) {
                    return null;
                }

                return [
                    'uid' => $event->uid,
                    'slug' => $event->slug,
                    'title' => $event->title,
                    'description' => $event->description,
                    'cover_image_url' => $event->cover_image_url,
                    'banner_image_url' => $event->banner_image_url,
                    'starts_at' => $event->starts_at ? Carbon::parse($event->starts_at)->format('Y-m-d\TH:i:s') : null,
                    'ends_at' => $event->ends_at ? Carbon::parse($event->ends_at)->format('Y-m-d\TH:i:s') : null,
                    'is_all_day' => (bool) $event->is_all_day,
                    'location' => $event->location ?: ($event->meeting_url ? 'En línea' : 'Por definir'),
                    'status' => 'Inscrito',
                    'registration_uid' => $reg->uid,
                    'registered_at' => $reg->registered_at,
                    'attended' => $reg->attended,
                ];
            })->filter()->values();

            return response()->json([
                'success' => true,
                'data' => $events,
            ]);

        } catch (\Exception $e) {
            \Log::error('🔥 ERROR AL CARGAR EVENTOS INSCRITOS: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los eventos inscritos.',
            ], 500);
        }
    }

    /**
     * 🔍 Verificar si un correo electrónico ya está registrado en el evento
     */
    public function verify(Request $request, $companyUid, $slug)
    {
        // 1. Validar que venga el correo
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        // 2. Resolver compañía y evento mediante el slug
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $event = OrgEvent::where('org_company_id', $company->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // 3. Buscar si existe un cliente con ese correo en la compañía
        $customer = OrgCustomer::where('org_company_id', $company->id)
            ->where('email', $validated['email'])
            ->first();

        if (! $customer) {
            return response()->json([
                'exists' => false,
                'message' => 'El correo no está registrado.',
            ], 404);
        }

        // 4. Buscar si ese cliente tiene un registro activo para este evento
        $registration = OrgEventRegistration::where('org_event_id', $event->id)
            ->where('org_customer_id', $customer->id)
            ->first();

        if (! $registration) {
            return response()->json([
                'exists' => false,
                'message' => 'El correo existe pero no está inscrito en este evento.',
            ], 404);
        }

        // 5. ¡Sí está registrado! Devolvemos su UID para el acceso directo
        return response()->json([
            'exists' => true,
            'message' => 'El usuario ya está registrado.',
            'data' => [
                'registration_uid' => $registration->uid,
                'first_name' => $customer->first_name,
                'email' => $customer->email,
            ],
        ], 200);
    }
    
}
