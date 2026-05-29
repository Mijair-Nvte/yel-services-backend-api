<?php

namespace App\Listeners;

use Laravel\Cashier\Events\WebhookReceived;
use App\Models\OrgSale;
use App\Models\OrgService;
use App\Models\OrgCompanyPartner;
use Illuminate\Support\Facades\Log;

class StripeEventListener
{
    public function handle(WebhookReceived $event)
    {
        $payload = $event->payload;

        if ($payload['type'] === 'checkout.session.completed') {
            $session = $payload['data']['object'];
            $metadata = $session['metadata'] ?? [];

            $serviceId = $metadata['service_id'] ?? null;
            $totalPaid = $session['amount_total'] / 100;

            // 1. Inicializamos valores
            $sellerId = null;
            $commissionAmount = 0;
            $referralCode = $metadata['referral_code'] ?? null;

            // 2. Si hay código de referido, buscamos al Partner y calculamos comisión
            if (!empty($referralCode)) {
                $partner = OrgCompanyPartner::where('referral_code', $referralCode)
                    ->where('is_active', true)
                    ->first();

                if ($partner) {
                    $sellerId = $partner->user_id;
                    
                    // Buscamos el servicio para aplicar el % configurado (Tu 15.00%)
                    $service = OrgService::find($serviceId);
                    if ($service) {
                        // Aquí toma el 'default_commission_value' (15.00) de tu DB
                        $commissionAmount = $totalPaid * ($service->default_commission_value / 100);
                    }
                }
            }

            // 3. Inserción Limpia
            try {
                OrgSale::create([
                    'org_company_id'    => $metadata['company_id'],
                    'org_service_id'    => $serviceId,
                    'source_type'       => 'stripe_ecommerce',
                    'source_id'         => $session['id'],
                    'customer_name'     => $session['customer_details']['name'] ?? 'Cliente Stripe',
                    'customer_email'    => $session['customer_details']['email'] ?? null,
                    'customer_phone'    => $session['customer_details']['phone'] ?? null,
                    'customer_origin'   => 'Storefront Web',
                    'product_name'      => $metadata['service_name'] ?? 'Servicio Profesional',
                    'total_amount'      => $totalPaid,
                    'seller_id'         => $sellerId,
                    'referral_code'     => $referralCode, // Guardamos el código para filtrar fácil
                    'commission_amount' => $commissionAmount,
                    'commission_status' => $sellerId ? 'pending' : 'not_applicable',
                ]);
                
                Log::info('✅ Venta y Comisión ('.$commissionAmount.') registradas automáticamente.');

            } catch (\Exception $e) {
                Log::error('❌ Error en Listener: ' . $e->getMessage());
            }
        }
    }
}