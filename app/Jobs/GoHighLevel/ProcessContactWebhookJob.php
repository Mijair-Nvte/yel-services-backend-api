<?php

namespace App\Jobs\GoHighLevel;

use App\Models\OrgCustomer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessContactWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        try {
            // 1. EL CORREO ES EL REY (Llave Primaria)
            // GHL a veces manda 'email' y a veces 'emailLowerCase' dependiendo del tipo de webhook.
            $email = $this->payload['email'] ?? $this->payload['emailLowerCase'] ?? null;
            $email = $email ? strtolower(trim($email)) : null;

            if (!$email) {
                Log::warning('GHL Contact Webhook ignorado: GHL no envió correo electrónico (Llave primaria obligatoria).');
                return;
            }

            $phone = $this->payload['phone'] ?? null;
            $ghlId = $this->payload['contact_id'] ?? $this->payload['id'] ?? null;

            // 2. Extraemos el origen
            $attribution = $this->payload['contact']['attributionSource'] ?? [];
            $source = $attribution['sessionSource'] ?? 'GHL Webhook';
            $medium = $attribution['medium'] ?? null;

            // 3. Extraemos y formateamos los TAGS
            $tagsRaw = $this->payload['tags'] ?? null;
            $tags = [];
            if (!empty($tagsRaw)) {
                $tags = array_map('trim', explode(',', $tagsRaw));
            }

            // 4. Extraemos los CUSTOM FIELDS de GHL
            $standardKeys = [
                'id', 'contact_id', 'first_name', 'last_name', 'full_name', 'email', 'emailLowerCase', 'phone', 
                'tags', 'country', 'date_created', 'dateAdded', 'full_address', 'contact_type', 
                'location', 'user', 'workflow', 'triggerData', 'contact', 'attributionSource', 
                'customData', 'city', 'timezone', 'contact_source', 'type', 'status', 'customFields'
            ];

            // AQUÍ ESTÁ EL TRUCO ANTI-BASURA: Extraemos TODOS los custom fields de GHL, 
            // incluso los que vienen vacíos (para que sobreescriban y borren a los viejos).
            $incomingCustomFields = collect($this->payload)
                ->except($standardKeys)
                ->toArray();

            // 5. BÚSQUEDA ESTRICTA POR CORREO
            $companyId = 1; // Ajusta según tu lógica multi-tenant

            $customer = OrgCustomer::where('org_company_id', $companyId)
                                   ->where('email', $email)
                                   ->first();

            $isNewRecord = false;
            
            if (!$customer) {
                $customer = new OrgCustomer();
                $customer->org_company_id = $companyId;
                $customer->email = $email;
                $isNewRecord = true;
            }

            // 6. ACTUALIZACIÓN DE DATOS BASE
            // Si GHL trae un dato válido, lo usamos. Si viene vacío, conservamos el que ya tenía Laravel.
            if ($ghlId) $customer->contact_id = $ghlId;
            
            if (!empty($this->payload['first_name'])) {
                $customer->first_name = $this->payload['first_name'];
            }
            if (!empty($this->payload['last_name'])) {
                $customer->last_name = $this->payload['last_name'];
            }
            if (!empty($phone)) {
                $customer->phone = $phone;
            }

            // 7. FUSIÓN INTELIGENTE DE METADATA (Limpieza de Basura)
            $existingMetadata = $customer->metadata ?? [];
            
            // A) Unir Tags (Los viejos + los nuevos sin repetir)
            $mergedTags = array_unique(array_merge($existingMetadata['tags'] ?? [], $tags));
            
            // B) Limpiar Custom Fields
            $existingCustomFields = $existingMetadata['custom_fields'] ?? [];
            
            // Fusionamos: Si GHL manda un campo vacío que Laravel tenía lleno, se volverá vacío.
            $mergedCustomFields = array_merge($existingCustomFields, $incomingCustomFields);
            
            // AHORA filtramos: Eliminamos cualquier campo que haya quedado nulo o vacío.
            $cleanCustomFields = array_filter($mergedCustomFields, function ($value) {
                return $value !== null && $value !== '';
            });

            // C) Asignamos la metadata final
            $customer->metadata = [
                'source'        => $existingMetadata['source'] ?? $source, // Conserva la fuente original de adquisición
                'medium'        => $existingMetadata['medium'] ?? $medium,
                'tags'          => array_values($mergedTags), // array_values reindexa el array
                'custom_fields' => $cleanCustomFields // Ya no hay datos basura 🎉
            ];

            // 8. FECHA DE CREACIÓN (Solo si es nuevo)
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
            ]);

        } catch (\Exception $e) {
            Log::error('Error procesando el Job ProcessContactWebhookJob: ' . $e->getMessage());
        }
    }
}