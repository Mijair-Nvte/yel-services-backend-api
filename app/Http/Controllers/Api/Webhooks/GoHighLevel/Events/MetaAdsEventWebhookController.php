<?php

namespace App\Http\Controllers\Api\Webhooks\GoHighLevel\Events;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use App\Models\OrgEventRegistration;
use App\Traits\HandlesCustomers;
use App\Jobs\GoHighLevel\Events\SendEventRegistrationToGHL; 
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MetaAdsEventWebhookController extends Controller
{
    use HandlesCustomers;

    public function handleRegistration(Request $request)
    {
        Log::info('Webhook recibido de GHL (Meta Ads - Eventos):', $request->all());

        try {
            // Extraemos los datos validando tanto la raíz como el objeto customData de GHL
            $eventUid = $request->input('event_uid') ?? $request->input('customData.event_uid');
            $email = $request->input('email') ?? $request->input('customData.email');
            $firstName = $request->input('first_name') ?? $request->input('customData.first_name') ?? 'Lead';
            $lastName = $request->input('last_name') ?? $request->input('customData.last_name') ?? '';
            $phone = $request->input('phone') ?? $request->input('customData.phone') ?? null;

            $ghlContactId = $request->input('contact_id');

            if (!$eventUid || !$email) {
                return response()->json(['error' => 'Faltan datos requeridos (event_uid o email)'], 400);
            }

            // 1. Buscamos el evento por su UID
            $event = OrgEvent::where('uid', $eventUid)->firstOrFail();

            // Buscamos la compañía asociada al evento para obtener su UID
            $company = OrgCompany::findOrFail($event->org_company_id);

            // 2. Procesamos al cliente (Busca o crea, asegurando datos limpios)
            $fullName = trim($firstName . ' ' . $lastName);
            
            $customerId = $this->findOrCreateCustomer(
                companyId: $company->id,
                fullName: $fullName,
                email: $email,
                phone: $phone,
                ghlContactId: $ghlContactId
            );

            // 3. Registramos al usuario en el evento (evitando duplicados)
            $registration = OrgEventRegistration::firstOrCreate(
                [
                    'org_event_id'    => $event->id,
                    'org_customer_id' => $customerId,
                ],
                [
                    'org_company_id'  => $company->id,
                    'registered_at'   => Carbon::now(),
                    'ticket_quantity' => 1,
                    'source'          => 'Meta Ads',
                    'notes'           => 'Registrado desde Formulario de Facebook Ads',
                ]
            );

            // 👇 VALIDACIÓN CLAVE: SI YA ESTABA REGISTRADO, DETENEMOS EL FLUJO AQUÍ 👇
            if (!$registration->wasRecentlyCreated) {
                Log::info("El contacto {$email} ya estaba registrado para el evento {$event->title}. Omitiendo reenvío a GHL.");
                
                return response()->json([
                    'success' => true,
                    'message' => 'El contacto ya estaba registrado previamente. No se requiere acción.',
                    'data' => [
                        'registration_uid' => $registration->uid,
                        'event_title'      => $event->title,
                    ]
                ], 200);
            }

            // ======================================================
            // 4. SI ES NUEVO, ENVIAMOS DATOS DE VUELTA A GOHIGHLEVEL
            // ======================================================
            
            // Crear tag único: slug + fecha (ej: comprar-casa-llc-2026-09-24)
            $eventDate = Carbon::parse($event->starts_at)->format('Y-m-d');
            $ghlTag = "{$event->slug}-{$eventDate}";

            $ghlPayload = [
                'first_name'       => $firstName,
                'last_name'        => $lastName,
                'email'            => $email,
                'phone'            => $phone ?? '',
                'tags'             => [$ghlTag], 
                'source'           => 'Meta Ads',
                'utm_source'       => $request->input('utm_source') ?? 'Facebook Ads',
                'utm_medium'       => $request->input('utm_medium') ?? 'cpc',
                'utm_campaign'     => $request->input('utm_campaign') ?? '',
                'event_title'      => $event->title,
                'event_date'       => $event->starts_at,
                'company_uid'      => $company->uid,
                'event_uid'        => $event->uid,
                'registration_uid' => $registration->uid,
            ];

            // Despachamos el Job para que regrese a GHL y dispare el Workflow 01
            SendEventRegistrationToGHL::dispatch($ghlPayload);

            return response()->json([
                'success' => true, 
                'message' => 'Registro de Meta Ads procesado y enviado a GHL correctamente',
                'data' => [
                    'registration_uid' => $registration->uid,
                    'event_title'      => $event->title,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error en Webhook de Meta Ads para eventos: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}