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
use Illuminate\Support\Facades\Log;

class ProcessContactWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HandlesCustomers;

    protected $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        try {
            // 1. Extracción de datos base del Webhook (Permitiendo vacíos para no perder leads de redes sociales)
            $email = $this->payload['email'] ?? $this->payload['emailLowerCase'] ?? null;
            $email = !empty($email) ? strtolower(trim($email)) : null;
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = null;
            }

            $phone = $this->payload['phone'] ?? null;
            $phone = !empty($phone) ? trim($phone) : null;

            $ghlId = $this->payload['contact_id'] ?? $this->payload['id'] ?? null;
            $ghlId = !empty($ghlId) ? trim($ghlId) : null;

            $firstName = $this->payload['first_name'] ?? $this->payload['firstName'] ?? '';
            $lastName = $this->payload['last_name'] ?? $this->payload['lastName'] ?? '';
            $fullName = trim("{$firstName} {$lastName}");
            if (empty($fullName)) {
                $fullName = 'Cliente GHL';
            }

            $companyId = 1; // Ajusta según tu lógica multi-tenant

            // 2. USAMOS EL TRAIT: Buscador / Creador inteligente unificado por ID, email o teléfono
            $customerId = $this->findOrCreateCustomer($companyId, $fullName, $email, $phone, $ghlId);
            $customer = OrgCustomer::find($customerId);

            if (!$customer) {
                Log::warning('GHL Contact Webhook: No se pudo encontrar o crear el cliente.', ['ghl_id' => $ghlId]);
                return;
            }

            // Determinamos si es un registro totalmente nuevo para la fecha histórica
            $isNewRecord = $customer->wasRecentlyCreated;

            // 3. Extraemos y formateamos los TAGS
            $tagsRaw = $this->payload['tags'] ?? [];
            $tags = [];
            if (is_array($tagsRaw)) {
                $tags = array_map('trim', $tagsRaw);
            } elseif (!empty($tagsRaw)) {
                $tags = array_map('trim', explode(',', $tagsRaw));
            }

            // 4. Extraemos los CUSTOM FIELDS de GHL (En webhook ya vienen descifrados)
            $standardKeys = [
                'id', 'contact_id', 'first_name', 'last_name', 'full_name', 'email', 'emailLowerCase', 'phone', 
                'tags', 'country', 'date_created', 'dateAdded', 'full_address', 'contact_type', 
                'location', 'user', 'workflow', 'triggerData', 'contact', 'attributionSource', 
                'customData', 'city', 'timezone', 'contact_source', 'type', 'status', 'customFields', 'source', 'utm_source', 'utmSource'
            ];

            $incomingCustomFields = collect($this->payload)
                ->except($standardKeys)
                ->toArray();

            if (isset($this->payload['customFields']) && is_array($this->payload['customFields'])) {
                foreach ($this->payload['customFields'] as $cf) {
                    $fieldName = $cf['name'] ?? $cf['id'] ?? null;
                    $fieldValue = $cf['value'] ?? null;
                    if ($fieldName) {
                        $incomingCustomFields[$fieldName] = is_array($fieldValue) && count($fieldValue) === 1 ? $fieldValue[0] : $fieldValue;
                    }
                }
            }

            // 5. EXTRACCIÓN DE LAS 3 FUENTES DE MARKETING
            $originValue = $incomingCustomFields['Origin'] ?? $incomingCustomFields['origin'] ?? null;
            $contactSourceValue = $this->payload['contact_source'] ?? $this->payload['source'] ?? $incomingCustomFields['contact_source'] ?? null;
            $utmSourceValue = $this->payload['utm_source'] ?? $this->payload['utmSource'] ?? $incomingCustomFields['utm_source'] ?? null;

            $primarySource = $originValue ?? $contactSourceValue ?? $utmSourceValue ?? 'GHL Webhook';

            // 6. FUSIÓN INTELIGENTE DE METADATA (Con los 3 campos de marketing limpios)
            $existingMetadata = $customer->metadata ?? [];
            
            $mergedTags = array_unique(array_merge($existingMetadata['tags'] ?? [], $tags));
            
            $existingCustomFields = $existingMetadata['custom_fields'] ?? [];
            $mergedCustomFields = array_merge($existingCustomFields, $incomingCustomFields);
            
            $cleanCustomFields = array_filter($mergedCustomFields, function ($value) {
                return $value !== null && $value !== '';
            });

            $customer->metadata = [
                'source'         => $existingMetadata['source'] ?? $primarySource,
                'origin'         => $originValue ?? $existingMetadata['origin'] ?? null,
                'contact_source' => $contactSourceValue ?? $existingMetadata['contact_source'] ?? null,
                'utm_source'     => $utmSourceValue ?? $existingMetadata['utm_source'] ?? null,
                'tags'           => array_values($mergedTags),
                'custom_fields'  => $cleanCustomFields
            ];

            // 7. FECHA DE CREACIÓN (Solo si es nuevo)
            if ($isNewRecord) {
                $creationDate = $this->payload['date_created'] ?? $this->payload['dateAdded'] ?? null;
                if (!empty($creationDate)) {
                    try {
                        $customer->created_at = Carbon::parse($creationDate)->setTimezone('America/Mexico_City');
                    } catch (\Exception $e) {
                        $customer->created_at = now();
                    }
                }
            }

            $customer->save();

            Log::info("GHL Contact Webhook procesado exitosamente.", [
                'customer_id' => $customer->id,
                'is_new'      => $isNewRecord,
                'email'       => $customer->email,
                'phone'       => $customer->phone,
                'origin'      => $originValue,
            ]);

        } catch (\Exception $e) {
            Log::error('Error procesando el Job ProcessContactWebhookJob: ' . $e->getMessage());
            $this->release(30);
        }
    }
}