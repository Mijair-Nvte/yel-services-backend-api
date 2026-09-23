<?php

namespace App\Jobs\GoHighLevel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchGhlContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $companyId;

    protected $nextPageUrl;

    // Aumentamos el timeout del Job por si la API de GHL tarda en responder
    public $timeout = 120;

    public function __construct($companyId, $nextPageUrl = null)
    {
        $this->companyId = $companyId;
        $this->nextPageUrl = $nextPageUrl;
    }

    public function handle(): void
    {
        // Traemos las credenciales de manera segura desde la configuración
        $token = config('services.ghl.token');
        $locationId = config('services.ghl.location_id');

        if (! $token || ! $locationId) {
            Log::error('❌ Error API GHL Sync: Faltan credenciales en el archivo .env');

            return;
        }

        // Si no hay URL previa, armamos la de la primera página
        $url = $this->nextPageUrl ?? "https://services.leadconnectorhq.com/contacts/?locationId={$locationId}&limit=100";
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Version' => '2021-07-28', // Versión obligatoria API v2
            ])->timeout(60)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $contacts = $data['contacts'] ?? [];

                if (! empty($contacts)) {
                    // Mandamos los 100 contactos a procesar
                    ProcessGhlContactsChunkJob::dispatch($this->companyId, $contacts);
                }

                // ¿Hay otra página? Despachamos otro Master Job a la cola
                $nextUrl = $data['meta']['nextPageUrl'] ?? null;

                if ($nextUrl) {
                    FetchGhlContactsJob::dispatch($this->companyId, $nextUrl);
                } else {
                    Log::info("✅ Sincronización masiva de GHL completada para company_id: {$this->companyId}");
                }
            } else {
                Log::error('❌ Error API GHL Sync: '.$response->body());
            }
        } catch (\Exception $e) {
            Log::error('❌ Excepción API GHL Sync: '.$e->getMessage());
            // Opcional: Podrías hacer un $this->release(60) para reintentar si hay error de red
        }
    }
}
