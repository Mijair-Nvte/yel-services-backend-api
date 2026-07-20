<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSaleToGHLDispatcherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $ghlPayload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $ghlPayload)
    {
        $this->ghlPayload = $ghlPayload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // El webhook "Enrutador" que crearemos en GHL
        $webhookUrl = config('services.ghl.inbound_webhook_url');

        if (!$webhookUrl) {
            Log::warning('⚠️ GHL Webhook URL no está configurada en services.php');
            return;
        }

        try {
            Log::info('🚀 Enviando datos de la venta al Dispatcher de GHL...', ['email' => $this->ghlPayload['email'] ?? '']);
            
            $response = Http::post($webhookUrl, $this->ghlPayload);

            if ($response->successful()) {
                Log::info('✅ Datos enviados a GHL con éxito.');
            } else {
                Log::error('❌ Fallo al enviar datos a GHL', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Excepción al conectar con GHL: ' . $e->getMessage());
        }
    }
}