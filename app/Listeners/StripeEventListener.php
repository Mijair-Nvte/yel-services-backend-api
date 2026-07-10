<?php

namespace App\Listeners;

use App\Services\SalesProcessing\SaleProcessingService; 
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    protected $saleProcessingService;

    // 👇 2. CORRECCIÓN: El método mágico en PHP debe ser __construct
    public function __construct(SaleProcessingService $saleProcessingService)
    {
        $this->saleProcessingService = $saleProcessingService;
    }

    public function handle(WebhookReceived $event)
    {
        $payload = $event->payload;

        if ($payload['type'] === 'checkout.session.completed') {
            $session = $payload['data']['object'];
            
            try {
                Log::info('📥 Procesando checkout.session.completed desde Stripe Webhook...');
                
                // Transferimos el control al servicio especializado
                $this->saleProcessingService->executeFromStripeCheckout($session);
                
                Log::info('🏁 Procesamiento de Checkout finalizado de manera conforme.');
                
            } catch (\Exception $e) {
                Log::error('❌ Error Crítico en StripeEventListener: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }
}