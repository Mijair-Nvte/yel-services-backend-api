<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use App\Models\OrgCustomer;
use App\Models\OrgEventRegistration;
use App\Traits\HandlesCustomers; 
use App\Jobs\GoHighLevel\Events\SendEventRegistrationToGHL; 
use Illuminate\Http\Request;
use Carbon\Carbon;

class EventRegistrationController extends Controller
{
    use HandlesCustomers; 

    public function store(Request $request, $companyUid, $slug)
    {
        // 1. Validar los datos de entrada del formulario
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'email'      => 'required|email|max:255',
            'phone'      => 'nullable|string|max:50',
            'source'     => 'nullable|string|max:100',
            'notes'      => 'nullable|string',
        ]);

        // 2. Resolver compañía y evento mediante el slug
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        
        $event = OrgEvent::where('org_company_id', $company->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // 3. Unimos nombre y apellido para pasárselo al trait
        $fullName = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));

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
                    'utm_source'   => $request->input('utm_source'),
                    'utm_medium'   => $request->input('utm_medium'),
                    'utm_campaign' => $request->input('utm_campaign'),
                ])
            ]);
        }

        // 4. Registrar la asistencia al evento (o recuperar la existente)
        $registration = OrgEventRegistration::firstOrCreate(
            [
                'org_event_id'    => $event->id,
                'org_customer_id' => $customerId,
            ],
            [
                'org_company_id'  => $company->id,
                'registered_at'   => Carbon::now(),
                'is_new_lead'     => $customer->wasRecentlyCreated ?? false,
                'ticket_quantity' => 1,
                'source'          => $validated['source'] ?? 'Directo',
                'notes'           => $validated['notes'] ?? null,
            ]
        );

        // 👇 VALIDACIÓN PARA EVITAR DUPLICADOS EN GHL 👇
        if (!$registration->wasRecentlyCreated) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico ya se encuentra registrado para este evento.',
                'data' => [
                    'registration_uid' => $registration->uid,
                    'event_title'      => $event->title,
                ]
            ], 409); // 409 Conflict: El recurso ya existe
        }

        // ======================================================
        // 5. ENVIAR DATOS A GOHIGHLEVEL MEDIANTE JOB (COLA)
        // ======================================================
        
        // Crear tag único: slug + fecha (ej: comprar-casa-llc-2026-09-24)
        $eventDate = Carbon::parse($event->starts_at)->format('Y-m-d');
        $ghlTag = "{$event->slug}-{$eventDate}";

        $ghlPayload = [
            'first_name'       => $validated['first_name'],
            'last_name'        => $validated['last_name'] ?? '',
            'email'            => $validated['email'],
            'phone'            => $validated['phone'] ?? '',
            'tags'             => [$ghlTag], 
            'source'           => $validated['source'] ?? 'Web Organica',
            'utm_source'       => $request->input('utm_source'),
            'utm_medium'       => $request->input('utm_medium'),
            'utm_campaign'     => $request->input('utm_campaign'),
            'event_title'      => $event->title,
            'event_date'       => $event->starts_at,
            'company_uid'      => $company->uid,
            'event_uid'        => $event->uid,
            'registration_uid' => $registration->uid,
        ];

        // Despachamos el Job para que corra en segundo plano
        SendEventRegistrationToGHL::dispatch($ghlPayload);

        return response()->json([
            'success' => true,
            'message' => '¡Te has registrado exitosamente al evento!',
            'data' => [
                'registration_uid' => $registration->uid,
                'event_title'      => $event->title,
                'customer_email'   => $customer->email ?? $validated['email']
            ]
        ], 201);
    }
}