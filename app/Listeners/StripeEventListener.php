<?php

namespace App\Listeners;

use App\Services\Academy\CourseEnrollmentService;
use App\Services\SalesProcessing\SaleProcessingService;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    protected $saleProcessingService;

    protected $courseEnrollmentService;

    // Inyectamos AMBOS servicios
    public function __construct(
        SaleProcessingService $saleProcessingService,
        CourseEnrollmentService $courseEnrollmentService
    ) {
        $this->saleProcessingService = $saleProcessingService;
        $this->courseEnrollmentService = $courseEnrollmentService;
    }

    public function handle(WebhookReceived $event)
    {
        $payload = $event->payload;

        if ($payload['type'] === 'checkout.session.completed') {
            $session = $payload['data']['object'];

            try {
                Log::info('📥 Procesando checkout.session.completed desde Stripe Webhook...');

                // Leemos el tipo de compra desde la metadata
                $purchaseType = $session['metadata']['type'] ?? null;

                if ($purchaseType === 'course_purchase') {
                    // 🎓 1. Es un curso: Delegamos al servicio de la Academia
                    Log::info('📚 Redirigiendo checkout a CourseEnrollmentService.');
                    $this->courseEnrollmentService->processCourseCheckout($session);
                } elseif ($purchaseType === 'service_purchase' || ! $purchaseType) {
                    // 💼 2. Es un servicio (o por defecto si no viene especificado)
                    Log::info('💼 Redirigiendo checkout a SaleProcessingService.');
                    $this->saleProcessingService->executeFromStripeCheckout($session);
                } else {
                    Log::warning('⚠️ Tipo de compra desconocido en metadata de Stripe: '.$purchaseType);
                }

                Log::info('🏁 Procesamiento de Checkout finalizado de manera conforme.');

            } catch (\Exception $e) {
                Log::error('❌ Error Crítico en StripeEventListener: '.$e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
