<?php

namespace App\Listeners;

use App\Models\OrgCompanyPartner;
use App\Models\OrgCustomer;
use App\Models\OrgSale;
use App\Models\OrgService;
use Illuminate\Support\Facades\Log; // 🌟 IMPORTANTE: Agregamos el modelo de clientes
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    public function handle(WebhookReceived $event)
    {
        $payload = $event->payload;

        if ($payload['type'] === 'checkout.session.completed') {
            $session = $payload['data']['object'];
            $metadata = $session['metadata'] ?? [];

            $serviceId = $metadata['service_id'] ?? null;
            $companyId = $metadata['company_id'] ?? null;
            $totalPaid = $session['amount_total'] / 100;

            if (! $companyId) {
                Log::error('❌ Error en Listener: No se recibió el company_id en la metadata de Stripe.');

                return;
            }

            // 1. Datos del Cliente desde Stripe
            $customerName = $session['customer_details']['name'] ?? 'Cliente Stripe';
            $customerEmail = $session['customer_details']['email'] ?? null;
            $customerPhone = $session['customer_details']['phone'] ?? null;

            // 🔍 PASO CLAVE: Buscar o registrar al cliente en el directorio central
            $customerId = $this->findOrCreateCustomer($companyId, $customerName, $customerEmail, $customerPhone);

            // 2. Inicializamos valores de comisiones
            $sellerId = null;
            $commissionAmount = 0;
            $referralCode = $metadata['referral_code'] ?? null;

            // 3. Si hay código de referido, buscamos al Partner y calculamos comisión
            if (! empty($referralCode)) {
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

            // 4. Inserción Limpia de la Venta
            try {
                $stripePaymentStatus = $session['payment_status'] === 'paid' ? 'paid' : 'pending';

                $sale = OrgSale::create([
                    'org_company_id' => $companyId,
                    'org_customer_id' => $customerId,
                    'org_service_id' => $serviceId,
                    'source_type' => 'stripe_ecommerce',
                    'source_id' => $session['id'],
                    'customer_origin' => 'Storefront Web',
                    'product_name' => $metadata['service_name'] ?? 'Servicio Profesional',
                    'total_amount' => $totalPaid,
                    'payment_status' => $stripePaymentStatus,
                    'seller_id' => $sellerId,
                    'referral_code' => $referralCode, // Guardamos el código para filtrar fácil
                    'commission_amount' => $commissionAmount,
                    'commission_status' => $sellerId ? 'pending' : 'not_applicable',
                ]);

                Log::info("✅ Venta UID {$sale->uid} (Pago: {$stripePaymentStatus}) y Comisión ({$commissionAmount}) registradas. Cliente ID: {$customerId}");

            } catch (\Exception $e) {
                Log::error('❌ Error en Listener al guardar Venta Stripe: '.$e->getMessage());
            }
        }
    }

    /**
     * Función interna para buscar o crear un cliente de manera limpia.
     */
    private function findOrCreateCustomer(int $companyId, string $fullName, ?string $email, ?string $phone): int
    {
        $customer = null;

        // 1. Intentar buscar por Email (es nuestro índice principal)
        if (! empty($email)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)
                ->where('email', $email)
                ->first();
        }

        // 2. Si no hay email o no se encontró, intentamos buscar por teléfono
        if (! $customer && ! empty($phone)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)
                ->where('phone', $phone)
                ->first();
        }

        // 3. Si el cliente ya existe, devolvemos su ID
        if ($customer) {
            Log::info("👤 Cliente Stripe existente encontrado: {$customer->first_name} (ID: {$customer->id})");

            return $customer->id;
        }

        // 4. Si es nuevo, separamos el nombre y lo registramos
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? null;

        $newCustomer = OrgCustomer::create([
            'org_company_id' => $companyId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
        ]);

        Log::info("👤 Nuevo cliente Stripe registrado (ID: {$newCustomer->id})");

        return $newCustomer->id;
    }
}
