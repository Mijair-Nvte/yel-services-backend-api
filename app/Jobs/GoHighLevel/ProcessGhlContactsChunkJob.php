<?php

namespace App\Jobs\GoHighLevel;

use App\Models\OrgCustomer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessGhlContactsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $companyId;

    protected $contactsChunk;

    public function __construct($companyId, array $contactsChunk)
    {
        $this->companyId = $companyId;
        $this->contactsChunk = $contactsChunk;
    }

    public function handle(): void
    {
        // 1. Obtenemos credenciales
        $token = config('services.ghl.token');
        $locationId = config('services.ghl.location_id');

        // 2. Traemos el diccionario de IDs => Nombres
        $customFieldsMap = $this->getCustomFieldsMap($token, $locationId);

        foreach ($this->contactsChunk as $contact) {
            try {
                $ghlId = $contact['id'] ?? null;

                // EL CORREO ES EL REY (Llave Única)
                $email = $contact['email'] ?? $contact['emailLowerCase'] ?? null;
                $email = $email ? strtolower(trim($email)) : null;

                // Si no hay correo, lo saltamos para evitar duplicados sin identificador fiable
                if (! $ghlId || ! $email) {
                    continue;
                }

                $tags = $contact['tags'] ?? [];
                $rawCustomFields = $contact['customFields'] ?? [];

                // 3. Traducimos los Custom Fields
                $incomingCustomFields = [];
                foreach ($rawCustomFields as $cf) {
                    $fieldId = $cf['id'] ?? '';
                    $fieldValue = $cf['value'] ?? null;

                    $fieldName = $customFieldsMap[$fieldId] ?? $fieldId;

                    if (is_array($fieldValue) && count($fieldValue) === 1) {
                        $fieldValue = $fieldValue[0];
                    }

                    $incomingCustomFields[$fieldName] = $fieldValue;
                }

                // 4. BÚSQUEDA Y UPSERT INTELIGENTE (Único por Compañía y Correo)
                $customer = OrgCustomer::where('org_company_id', $this->companyId)
                    ->where('email', $email)
                    ->first();

                $isNewRecord = false;
                if (! $customer) {
                    $customer = new OrgCustomer;
                    $customer->org_company_id = $this->companyId;
                    $customer->email = $email;
                    $isNewRecord = true;
                }

                // Actualizamos datos básicos si vienen de GHL
                $customer->contact_id = $ghlId;
                if (! empty($contact['firstName']) || ! empty($contact['firstNameLowerCase'])) {
                    $customer->first_name = $contact['firstName'] ?? $contact['firstNameLowerCase'];
                }
                if (! empty($contact['lastName']) || ! empty($contact['lastNameLowerCase'])) {
                    $customer->last_name = $contact['lastName'] ?? $contact['lastNameLowerCase'];
                }
                if (! empty($contact['phone'])) {
                    $customer->phone = $contact['phone'];
                }

                // 5. EXTRACCIÓN Y CONSOLIDACIÓN DE LAS FUENTES DE MARKETING
                // Extraemos los valores específicos de cada fuente
                $originValue = $incomingCustomFields['Origin'] ?? $incomingCustomFields['origin'] ?? null;
                $contactSourceValue = $contact['source'] ?? $contact['contactSource'] ?? null;
                $utmSourceValue = $contact['utmSource'] ?? $contact['utm_source'] ?? null;

                // Definimos un source principal jerárquico para reportes rápidos si se requiere
                $primarySource = $originValue ?? $contactSourceValue ?? $utmSourceValue ?? 'Orgánico / GHL';

                // 6. FUSIÓN INTELIGENTE DE METADATA
                $existingMetadata = $customer->metadata ?? [];

                // Unir Tags sin repetir
                $mergedTags = array_unique(array_merge($existingMetadata['tags'] ?? [], $tags));

                // Fusionar y limpiar Custom Fields (Elimina basura borrada en GHL)
                $mergedCustomFields = array_merge($existingMetadata['custom_fields'] ?? [], $incomingCustomFields);
                $cleanCustomFields = array_filter($mergedCustomFields, function ($value) {
                    return $value !== null && $value !== '';
                });

                $customer->metadata = [
                    'source' => $existingMetadata['source'] ?? $primarySource,
                    'origin' => $originValue,         // Tu campo personalizado select ('Facebook KCH', etc.)
                    'contact_source' => $contactSourceValue,  // El campo por defecto de GHL ('Form Mkt Lead', etc.)
                    'utm_source' => $utmSourceValue,      // Los parámetros UTM de la URL
                    'tags' => array_values($mergedTags),
                    'custom_fields' => $cleanCustomFields,
                ];

                // 7. FECHAS HISTÓRICAS EXACTAS (Solo para registros nuevos)
                if ($isNewRecord && ! empty($contact['dateAdded'])) {
                    try {
                        $customer->created_at = Carbon::parse($contact['dateAdded'])->setTimezone('America/Mexico_City');
                        $customer->updated_at = $customer->created_at;

                        // Desactivamos timestamps temporalmente para que Eloquent respete la fecha histórica
                        $customer->timestamps = false;
                    } catch (\Exception $e) {
                        $customer->created_at = now();
                    }
                }

                // Guardamos el cliente (Si ya existía, actualiza sus datos conservando su created_at original)
                $customer->save();
            } catch (\Exception $e) {
                // Si un contacto individual falla, lo registramos en el log pero dejamos que el chunk siga su curso
                Log::error("⚠️ Error procesando contacto individual de GHL [Email: {$email}]: ".$e->getMessage());
                $this->release(30);
            }
        }
    }

    protected function getCustomFieldsMap($token, $locationId)
    {
        if (! $token || ! $locationId) {
            return [];
        }

        return Cache::remember('ghl_custom_fields_map_'.$locationId, 3600, function () use ($token, $locationId) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Version' => '2021-07-28',
                ])->get("https://services.leadconnectorhq.com/locations/{$locationId}/customFields");

                $map = [];
                if ($response->successful()) {
                    $fields = $response->json()['customFields'] ?? [];
                    foreach ($fields as $field) {
                        $map[$field['id']] = $field['name'];
                    }
                }

                return $map;
            } catch (\Exception $e) {
                Log::error('❌ Excepción en Custom Fields: '.$e->getMessage());

                return [];
            }
        });
    }
}
