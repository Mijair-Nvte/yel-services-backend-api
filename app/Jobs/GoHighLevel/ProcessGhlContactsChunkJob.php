<?php

namespace App\Jobs\GoHighLevel;

use App\Models\OrgCustomer;
use App\Traits\HandlesCustomers;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HandlesCustomers;

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

        // 2. Traemos el diccionario de IDs => Nombres de Custom Fields
        $customFieldsMap = $this->getCustomFieldsMap($token, $locationId);

        foreach ($this->contactsChunk as $item) {
            try {
                // Soportar tanto si viene envuelto en 'contact' como si viene plano
                $contact = $item['contact'] ?? $item;

                $ghlId = $contact['id'] ?? null;
                $ghlId = !empty($ghlId) ? trim($ghlId) : null;

                if (!$ghlId) {
                    continue;
                }

                // Correo y Teléfono
                $email = $contact['email'] ?? $contact['emailLowerCase'] ?? null;
                $email = !empty($email) ? strtolower(trim($email)) : null;
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = null;
                }

                $phone = $contact['phone'] ?? null;
                $phone = !empty($phone) ? trim($phone) : null;

                $firstName = $contact['firstName'] ?? $contact['firstNameLowerCase'] ?? '';
                $lastName = $contact['lastName'] ?? $contact['lastNameLowerCase'] ?? '';
                $fullName = trim("{$firstName} {$lastName}");
                if (empty($fullName)) {
                    $fullName = 'Cliente GHL';
                }

                $tags = $contact['tags'] ?? [];
                if (is_string($tags)) {
                    $tags = array_map('trim', explode(',', $tags));
                }

                $rawCustomFields = $contact['customFields'] ?? [];

                // 3. Traducimos y aplanamos los Custom Fields
                $incomingCustomFields = [];
                foreach ($rawCustomFields as $cf) {
                    $fieldId = $cf['id'] ?? '';
                    $fieldValue = $cf['value'] ?? null;
                    
                    $fieldName = $customFieldsMap[$fieldId] ?? $fieldId;

                    // Si viene como array (ej. de selección múltiple), lo unimos o tomamos el primero
                    if (is_array($fieldValue)) {
                        $fieldValue = count($fieldValue) === 1 ? $fieldValue[0] : implode(', ', $fieldValue);
                    }

                    $incomingCustomFields[$fieldName] = $fieldValue;
                }

                // 4. USAMOS EL TRAIT: Búsqueda unificada por contact_id, email o teléfono
                $customerId = $this->findOrCreateCustomer($this->companyId, $fullName, $email, $phone, $ghlId);
                $customer = OrgCustomer::find($customerId);

                if (!$customer) {
                    continue;
                }

                $isNewRecord = $customer->wasRecentlyCreated;

                // 5. EXTRACCIÓN Y CONSOLIDACIÓN DE LAS FUENTES DE MARKETING
                $originValue = $incomingCustomFields['Origin'] ?? $incomingCustomFields['origin'] ?? null;
                
                // Extracción de attributionSource si existe
                $attribution = $contact['attributionSource'] ?? [];
                $sessionSource = $attribution['sessionSource'] ?? null;
                $medium = $attribution['medium'] ?? null;

                $contactSourceValue = $contact['source'] ?? $contact['contactSource'] ?? $incomingCustomFields['contact_source'] ?? $sessionSource ?? null;
                $utmSourceValue = $contact['utmSource'] ?? $contact['utm_source'] ?? $incomingCustomFields['utm_source'] ?? null;

                $primarySource = $originValue ?? $contactSourceValue ?? $utmSourceValue ?? 'Orgánico / GHL';

                // 6. FUSIÓN INTELIGENTE DE METADATA
                $existingMetadata = $customer->metadata ?? [];

                $mergedTags = array_unique(array_merge($existingMetadata['tags'] ?? [], $tags));

                $mergedCustomFields = array_merge($existingMetadata['custom_fields'] ?? [], $incomingCustomFields);
                $cleanCustomFields = array_filter($mergedCustomFields, function ($value) {
                    return $value !== null && $value !== '';
                });

                $customer->metadata = [
                    'source'         => $primarySource ?? $existingMetadata['source'] ?? 'Orgánico / GHL',
                    'origin'         => $originValue ?? $existingMetadata['origin'] ?? null,
                    'contact_source' => $contactSourceValue ?? $existingMetadata['contact_source'] ?? null,
                    'utm_source'     => $utmSourceValue ?? $existingMetadata['utm_source'] ?? null,
                    'tags'           => array_values($mergedTags),
                    'custom_fields'  => $cleanCustomFields,
                ];

                // 7. FECHAS HISTÓRICAS EXACTAS (Aplica para nuevos y existentes para corregir el histórico)
                $ghlDateAdded = $contact['dateAdded'] ?? $contact['date_created'] ?? null;
                if (!empty($ghlDateAdded)) {
                    try {
                        $parsedDate = Carbon::parse($ghlDateAdded)->setTimezone('America/Mexico_City');
                        $customer->created_at = $parsedDate;
                        $customer->updated_at = $parsedDate;
                        $customer->timestamps = false; // Desactiva timestamps automáticos para respetar la fecha de GHL
                    } catch (\Exception $e) {
                        if ($isNewRecord) {
                            $customer->created_at = now();
                        }
                    }
                }

                $customer->save();

            } catch (\Exception $e) {
                Log::error("⚠️ Error procesando contacto individual de GHL [GHL ID: {$ghlId}]: ".$e->getMessage());
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