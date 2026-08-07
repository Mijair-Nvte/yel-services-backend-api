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
    protected ?string $webhookUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(array $ghlPayload, ?string $webhookUrl = null)
    {
        $this->ghlPayload = $ghlPayload;
        
        // Si mandamos una URL la usamos, si no, usamos la de ventas por defecto
        $this->webhookUrl = $webhookUrl ?? config('services.ghl.inbound_webhook_url');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->webhookUrl) {
            Log::warning('⚠️ GHL Webhook URL no está configurada.');
            return;
        }

        try {
            Log::info('🚀 Enviando datos al Dispatcher de GHL...', [
                'email' => $this->ghlPayload['email'] ?? '',
                'webhook_url' => $this->webhookUrl // Para que veas en los logs a qué URL se mandó
            ]);
            
            $response = Http::post($this->webhookUrl, $this->ghlPayload);

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