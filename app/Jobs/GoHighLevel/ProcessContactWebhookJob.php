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
            $email = $this->payload['email'] ?? null;
            $phone = $this->payload['phone'] ?? null;
            $ghlId = $this->payload['contact_id'] ?? null;

            if (!$email && !$phone && !$ghlId) {
                Log::warning('GHL Contact Webhook ignorado: Sin contact_id, email ni teléfono.', ['ghl_id' => $ghlId]);
                return;
            }

            // 1. Prioridad de búsqueda
            if ($ghlId) {
                $searchKey = ['contact_id' => $ghlId];
            } elseif ($email) {
                $searchKey = ['email' => $email];
            } else {
                $searchKey = ['phone' => $phone];
            }

            // 2. Extraemos el origen
            $attribution = $this->payload['contact']['attributionSource'] ?? [];
            $source = $attribution['sessionSource'] ?? 'GHL Webhook';
            $medium = $attribution['medium'] ?? null;

            // 3. Extraemos y formateamos los TAGS (En Webhook vienen como string: "tag1, tag2")
            $tagsRaw = $this->payload['tags'] ?? null;
            $tags = [];
            if (!empty($tagsRaw)) {
                $tags = array_map('trim', explode(',', $tagsRaw));
            }

            // 4. Extraemos los CUSTOM FIELDS dinámicamente limpiando el payload
            $standardKeys = [
                'id', 'contact_id', 'first_name', 'last_name', 'full_name', 'email', 'phone', 
                'tags', 'country', 'date_created', 'full_address', 'contact_type', 
                'location', 'user', 'workflow', 'triggerData', 'contact', 'attributionSource', 
                'customData', 'city', 'timezone', 'contact_source', 'type', 'status'
            ];

            // Todo lo que NO sea una llave estándar, es un Custom Field.
            // filter() quita automáticamente los que vengan vacíos o nulos.
            $customFields = collect($this->payload)->except($standardKeys)->filter(function ($value) {
                return $value !== null && $value !== '';
            })->toArray();

            // 5. Instanciamos el cliente
            $customer = OrgCustomer::firstOrNew($searchKey);

            $customer->org_company_id = 1;
            $customer->contact_id     = $ghlId;
            $customer->first_name     = $this->payload['first_name'] ?? $customer->first_name;
            $customer->last_name      = $this->payload['last_name'] ?? $customer->last_name;
            $customer->email          = $email ?? $customer->email;
            $customer->phone          = $phone ?? $customer->phone;
            
            // 6. Asignamos la metadata con la misma estructura que la Sincronización Masiva
            $customer->metadata = [
                'source'        => $source,
                'medium'        => $medium,
                'tags'          => $tags,
                'custom_fields' => $customFields
            ];

            // 7. Protegemos el historial de creación
            if (!$customer->exists && !empty($this->payload['date_created'])) {
                try {
                    $customer->created_at = Carbon::parse($this->payload['date_created'])->setTimezone('America/Mexico_City');
                } catch (\Exception $e) {
                    $customer->created_at = now();
                }
            }

            $customer->save();

        } catch (\Exception $e) {
            Log::error('Error procesando el Job ProcessContactWebhookJob: ' . $e->getMessage());
        }
    }
}