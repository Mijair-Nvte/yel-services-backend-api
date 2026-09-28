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

    // Aumentamos los intentos y el timeout por seguridad ante volúmenes grandes
    public $tries = 5;
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

            // 🛑 1. PROTECCIÓN CONTRA RATE LIMIT (429 Too Many Requests)
            if ($response->status() === 429) {
                Log::warning('⚠️ GHL Rate Limit alcanzado (429). Pausando sincronización por 30 segundos...');
                // Regresa el Job a la cola y reintenta en 30 segundos sin romper el flujo
                $this->release(30);
                return;
            }

            if ($response->successful()) {
                $data = $response->json();
                $contacts = $data['contacts'] ?? [];

                if (! empty($contacts)) {
                    // Mandamos los 100 contactos a procesar en segundo plano
                    ProcessGhlContactsChunkJob::dispatch($this->companyId, $contacts);
                }

                // ¿Hay otra página? Despachamos el siguiente bloque
                $nextUrl = $data['meta']['nextPageUrl'] ?? null;

                if ($nextUrl) {
                    // ⏱️ 2. PAUSA DE SEGURIDAD (500 milisegundos)
                    // Evita ráfagas agresivas que disparen el bloqueo en GHL
                    usleep(500000); 

                    FetchGhlContactsJob::dispatch($this->companyId, $nextUrl);
                } else {
                    Log::info("✅ Sincronización masiva de GHL completada para company_id: {$this->companyId}");
                }
            } else {
                Log::error('❌ Error API GHL Sync: '.$response->body());
            }
        } catch (\Exception $e) {
            Log::error('❌ Excepción API GHL Sync: '.$e->getMessage());
            
            // Si ocurre un error de red imprevisto, reintentamos en 60 segundos
            $this->release(60);
        }
    }
}