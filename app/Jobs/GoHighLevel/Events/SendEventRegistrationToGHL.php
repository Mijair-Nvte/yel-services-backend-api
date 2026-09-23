<?php

namespace App\Jobs\GoHighLevel\Events;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SendEventRegistrationToGHL implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;

    /**
     * Número de veces que el trabajo puede intentarse si falla la conexión a GHL.
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Obtener la URL del Webhook Inbound de GHL desde el .env
        $webhookUrl = env('GHL_EVENT_REGISTRATION_WEBHOOK_URL');

        if (!$webhookUrl) {
            Log::warning('No se configuró GHL_EVENT_REGISTRATION_WEBHOOK_URL en el .env');
            return;
        }

        // Enviamos el POST al webhook de GHL
        $response = Http::timeout(10)->post($webhookUrl, $this->payload);

        // Si falla (Status 4xx o 5xx), registramos el error y lanzamos excepción
        // Esto le indica a Laravel Queue que el Job falló y debe reintentarlo
        if ($response->failed()) {
            Log::error('Fallo al enviar registro de evento a GoHighLevel', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $this->payload
            ]);
            
            throw new Exception('Error al comunicarse con el Webhook de GHL. Status: ' . $response->status());
        }
    }
}