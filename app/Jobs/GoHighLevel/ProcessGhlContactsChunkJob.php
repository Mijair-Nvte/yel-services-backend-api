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
        $insertData = [];
        $now = now()->toDateTimeString();

        // 1. Obtenemos credenciales para poder descargar el diccionario de Custom Fields
        $token = config('services.ghl.token');
        $locationId = config('services.ghl.location_id');

        // 2. Traemos el diccionario de IDs => Nombres (Usando caché de 1 hora para no saturar la API)
        $customFieldsMap = $this->getCustomFieldsMap($token, $locationId);

        foreach ($this->contactsChunk as $contact) {
            $ghlId = $contact['id'] ?? null;
            if (!$ghlId) continue;

            // Log crudo para inspeccionar cómo viene exactamente de GHL antes de procesarlo
            // Log::debug("GHL Sync Crudo Contacto [{$ghlId}]:", $contact);

            // Mapeo de fecha original
            $createdAt = now();
            if (!empty($contact['dateAdded'])) {
                try {
                    $createdAt = Carbon::parse($contact['dateAdded'])->setTimezone('America/Mexico_City');
                } catch (\Exception $e) {
                    // Mantiene la fecha actual si falla el parseo
                }
            }

            $tags = $contact['tags'] ?? [];
            $rawCustomFields = $contact['customFields'] ?? [];

            // 3. Traducimos los Custom Fields
            $mappedCustomFields = [];
            foreach ($rawCustomFields as $cf) {
                $fieldId = $cf['id'] ?? '';
                $fieldValue = $cf['value'] ?? null;
                
                // Si el ID existe en nuestro diccionario, usamos su nombre, si no, dejamos el ID por defecto
                $fieldName = $customFieldsMap[$fieldId] ?? $fieldId;

                // GHL a veces manda los valores en arreglos de un solo item (ej: ["WhatsApp chat"])
                // Esto lo aplana para que quede como un texto simple: "WhatsApp chat"
                if (is_array($fieldValue) && count($fieldValue) === 1) {
                    $fieldValue = $fieldValue[0];
                }

                $mappedCustomFields[$fieldName] = $fieldValue;
            }

            // Log procesado para ver cómo quedó la traducción
            Log::info("GHL Sync Procesado [{$ghlId}]:", [
                'email' => $contact['email'] ?? 'Sin email',
                'custom_fields_traducidos' => $mappedCustomFields
            ]);

            // Armamos el metadata estructurado
            $metadata = [
                'source'        => 'GHL Historical Sync',
                'tags'          => $tags,
                'custom_fields' => $mappedCustomFields
            ];

            // Preparamos el array de inserción para MySQL
            $insertData[] = [
                'uid'            => 'cus_' . strtoupper(Str::random(16)),
                'org_company_id' => $this->companyId,
                'contact_id'     => $ghlId,
                'email'          => $contact['email'] ?? strtolower($contact['emailLowerCase'] ?? null),
                'phone'          => $contact['phone'] ?? null,
                'first_name'     => $contact['firstName'] ?? $contact['firstNameLowerCase'] ?? '',
                'last_name'      => $contact['lastName'] ?? $contact['lastNameLowerCase'] ?? '',
                'metadata'       => json_encode($metadata),
                'created_at'     => $createdAt->toDateTimeString(),
                'updated_at'     => $now,
            ];
        }

        if (!empty($insertData)) {
            try {
                OrgCustomer::insertOrIgnore($insertData);
            } catch (\Exception $e) {
                Log::error('❌ Error en insert masivo GHL: ' . $e->getMessage());
            }
        }
    }

    /**
     * Descarga y mapea los Custom Fields de GoHighLevel (ID => Nombre Real)
     */
    protected function getCustomFieldsMap($token, $locationId)
    {
        if (!$token || !$locationId) return [];

        // Guardamos el resultado en caché por 3600 segundos (1 hora)
        // Así los siguientes 1040 jobs leerán la memoria de tu servidor y no harán peticiones a GHL.
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
                        // Creamos el diccionario: ['856qPquvAJB8OZP9Igw3' => 'Origen']
                        $map[$field['id']] = $field['name'];
                    }
                    Log::info("✅ Diccionario de Custom Fields descargado y guardado en caché.");
                } else {
                    Log::error("❌ Error bajando Custom Fields: " . $response->body());
                }
                return $map;
            } catch (\Exception $e) {
                Log::error("❌ Excepción en Custom Fields: " . $e->getMessage());
                return [];
            }
        });
    }
}