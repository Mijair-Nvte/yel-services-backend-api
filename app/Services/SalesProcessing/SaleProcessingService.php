<?php

namespace App\Services\SalesProcessing;

use App\Models\OrgCompany;
use App\Models\OrgCompanyPartner;
use App\Models\OrgCustomer;
use App\Models\OrgSale;
use App\Models\OrgService;
use App\Models\OrgServiceOrder;
use App\Models\User;
use App\Mail\Store\ServicePurchaseSuccessMail;
use App\Mail\Store\ServicePurchaseFailedMail;
use App\Mail\Store\InternalSaleNotificationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SaleProcessingService
{
    /**
     * Orquesta el registro completo de la venta, cliente y herencia de orden de servicio.
     */
    public function executeFromStripeCheckout(array $session): void
    {
        $metadata = $session['metadata'] ?? [];
        $companyId = $metadata['company_id'] ?? null;
        $serviceId = $metadata['service_id'] ?? null;
        $totalPaid = $session['amount_total'] / 100;

        if (!$companyId) {
            Log::error('❌ Error en SaleProcessingService: No se recibió el company_id en la metadata.');
            return;
        }

        // Datos del cliente desde el evento
        $customerName = $session['customer_details']['name'] ?? 'Cliente Stripe';
        $customerEmail = $session['customer_details']['email'] ?? null;
        $customerPhone = $session['customer_details']['phone'] ?? null;

        // Ejecutamos todo dentro de una transacción atómica
        DB::transaction(function () use ($session, $metadata, $companyId, $serviceId, $totalPaid, $customerName, $customerEmail, $customerPhone) {
            
            // 1. Buscar o registrar al cliente
            $customerId = $this->findOrCreateCustomer($companyId, $customerName, $customerEmail, $customerPhone);

            // 2. Calcular Comisiones de Afiliados
            $sellerId = null;
            $commissionAmount = 0;
            $referralCode = $metadata['referral_code'] ?? null;

            $service = OrgService::find($serviceId);

            if (!empty($referralCode)) {
                $partner = OrgCompanyPartner::where('referral_code', $referralCode)
                    ->where('is_active', true)
                    ->first();

                if ($partner && $service) {
                    $sellerId = $partner->user_id;
                    $commissionAmount = $totalPaid * ($service->default_commission_value / 100);
                }
            }

            // 3. Crear el Registro de la Venta
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
                'referral_code' => $referralCode,
                'commission_amount' => $commissionAmount,
                'commission_status' => $sellerId ? 'pending' : 'not_applicable',
            ]);

            // 4. 🚀 LA PIEZA CLAVE: Clonación Automática y Creación de la Orden de Trabajo
            if ($stripePaymentStatus === 'paid' && $service) {
                $this->createServiceOrder($sale, $service, $customerId, $companyId);
            }

            // 5. Despacho de Notificaciones (Mails asíncronos en Queue)
            $this->dispatchNotifications($sale, $stripePaymentStatus, $customerEmail, $customerName, $companyId, $sellerId);
        });
    }

    /**
     * Genera la instancia operativa de la orden heredando los responsables del catálogo
     */
    private function createServiceOrder(OrgSale $sale, OrgService $service, int $customerId, int $companyId): void
    {
        // Creamos la orden instanciando los datos actuales de la plantilla
        $order = OrgServiceOrder::create([
            'org_company_id'  => $companyId,
            'org_sale_id'     => $sale->id,
            'org_service_id'  => $service->id,
            'org_customer_id' => $customerId,
            'assigned_to'     => $service->default_assignee_id, // Hereda el Owner asignado por defecto
            'status'          => 'pending',
            'metadata'        => [
                'initiated_by' => 'stripe_webhook',
                'cloned_at'    => now()->toDateTimeString()
            ]
        ]);

        // Extraemos los IDs de los seguidores del catálogo original
        $defaultFollowerIds = $service->defaultFollowers()->pluck('users.id')->toArray();

        if (!empty($defaultFollowerIds)) {
            // Sincronizamos los seguidores en el nuevo pivote operacional de la orden
            $order->followers()->sync($defaultFollowerIds);
        }

        Log::info("⚙️ Orden de Trabajo generada con éxito: UID {$order->uid} asignada al usuario ID {$service->default_assignee_id}");
    }

    /**
     * Resuelve de forma limpia la existencia del cliente central
     */
    private function findOrCreateCustomer(int $companyId, string $fullName, ?string $email, ?string $phone): int
    {
        $customer = null;

        if (!empty($email)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)->where('email', $email)->first();
        }

        if (!$customer && !empty($phone)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)->where('phone', $phone)->first();
        }

        if ($customer) {
            return $customer->id;
        }

        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? null;

        $newCustomer = OrgCustomer::create([
            'org_company_id' => $companyId,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'email'          => $email,
            'phone'          => $phone,
        ]);

        return $newCustomer->id;
    }

    /**
     * Centraliza el envío de correspondencia y alertas
     */
    private function dispatchNotifications(OrgSale $sale, string $status, ?string $customerEmail, string $customerName, int $companyId, ?int $sellerId): void
    {
        if ($status === 'paid') {
            if ($customerEmail) {
                Mail::to($customerEmail)->send(new ServicePurchaseSuccessMail($sale, $customerName));
            }

            $company = OrgCompany::find($companyId);
            if ($company && $company->owner_id) {
                $adminUser = User::find($company->owner_id);
                if ($adminUser) {
                    Mail::to($adminUser->email)->send(new InternalSaleNotificationMail($sale, $adminUser->name, 'Administrador'));
                }
            }

            if ($sellerId) {
                $sellerUser = User::find($sellerId);
                if ($sellerUser) {
                    Mail::to($sellerUser->email)->send(new InternalSaleNotificationMail($sale, $sellerUser->name, 'Afiliado'));
                }
            }
        } else {
            if ($customerEmail) {
                Mail::to($customerEmail)->send(new ServicePurchaseFailedMail($sale, $customerName));
            }
        }
    }
}