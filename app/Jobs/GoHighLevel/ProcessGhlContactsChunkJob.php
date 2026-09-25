<?php

namespace App\Jobs\GoHighLevel;

use App\Models\OrgCustomer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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
            $ghlId = $contact['id'] ?? null;
            
            // EL CORREO ES EL REY
            $email = $contact['email'] ?? $contact['emailLowerCase'] ?? null;
            $email = $email ? strtolower(trim($email)) : null;

            // Si no hay correo, lo saltamos para evitar duplicados sin identificador fiable
            if (!$ghlId || !$email) continue;

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

            // 4. BÚSQUEDA Y UPSERT INTELIGENTE
            $customer = OrgCustomer::where('org_company_id', $this->companyId)
                                   ->where('email', $email)
                                   ->first();

            $isNewRecord = false;
            if (!$customer) {
                $customer = new OrgCustomer();
                $customer->org_company_id = $this->companyId;
                $customer->email = $email;
                $isNewRecord = true;
            }

            // Actualizamos datos básicos si vienen de GHL
            $customer->contact_id = $ghlId;
            if (!empty($contact['firstName']) || !empty($contact['firstNameLowerCase'])) {
                $customer->first_name = $contact['firstName'] ?? $contact['firstNameLowerCase'];
            }
            if (!empty($contact['lastName']) || !empty($contact['lastNameLowerCase'])) {
                $customer->last_name = $contact['lastName'] ?? $contact['lastNameLowerCase'];
            }
            if (!empty($contact['phone'])) {
                $customer->phone = $contact['phone'];
            }

            // 5. FUSIÓN INTELIGENTE DE METADATA
            $existingMetadata = $customer->metadata ?? [];
            
            // Unir Tags sin repetir
            $mergedTags = array_unique(array_merge($existingMetadata['tags'] ?? [], $tags));
            
            // Fusionar y limpiar Custom Fields (Elimina basura borrada en GHL)
            $mergedCustomFields = array_merge($existingMetadata['custom_fields'] ?? [], $incomingCustomFields);
            $cleanCustomFields = array_filter($mergedCustomFields, function ($value) {
                return $value !== null && $value !== '';
            });

            $customer->metadata = [
                'source'        => $existingMetadata['source'] ?? 'GHL Historical Sync',
                'tags'          => array_values($mergedTags),
                'custom_fields' => $cleanCustomFields
            ];

            // 6. Fechas
            if ($isNewRecord && !empty($contact['dateAdded'])) {
                try {
                    $customer->created_at = Carbon::parse($contact['dateAdded'])->setTimezone('America/Mexico_City');
                } catch (\Exception $e) {
                    $customer->created_at = now();
                }
            }

            $customer->save();
        }
    }

    protected function getCustomFieldsMap($token, $locationId)
    {
        if (!$token || !$locationId) return [];

        return Cache::remember('ghl_custom_fields_map_' . $locationId, 3600, function () use ($token, $locationId) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Version' => '2021-07-28'
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
                Log::error("❌ Excepción en Custom Fields: " . $e->getMessage());
                return [];
            }
        });
    }
}