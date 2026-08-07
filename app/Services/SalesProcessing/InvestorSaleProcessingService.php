<?php

namespace App\Services\SalesProcessing;

use App\Mail\Store\InternalSaleNotificationMail;
use App\Mail\Store\ServicePurchaseSuccessMail;
use App\Models\OrgCompany;
use App\Models\OrgCustomer;
use App\Models\OrgSale;
use App\Models\OrgService;
use App\Models\OrgServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvestorSaleProcessingService
{
    /**
     * Orquesta el registro de la venta de un servicio para un inversor autenticado.
     */
    public function executeFromStripeCheckout(array $session): void
    {
        $metadata = $session['metadata'] ?? [];
        $companyId = $metadata['company_id'] ?? null;
        $serviceId = $metadata['service_id'] ?? null;
        $userId = $metadata['user_id'] ?? null; // ID del User autenticado

        $totalPaid = $session['amount_total'] / 100;
        $stripeSessionId = $session['id'] ?? null;

        if (! $companyId || ! $userId) {
            Log::error('❌ Error en InvestorSaleProcessingService: Falta company_id o user_id en metadata.', ['session_id' => $stripeSessionId]);

            return;
        }

        // Datos del checkout de Stripe
        $customerEmail = $session['customer_details']['email'] ?? null;
        $customerPhone = $session['customer_details']['phone'] ?? null;
        $stripePaymentStatus = $session['payment_status'] === 'paid' ? 'paid' : 'pending';

        // Obtenemos el usuario autenticado que hizo la compra
        $user = User::find($userId);
        $service = OrgService::find($serviceId);

        $sale = null;

        try {
            DB::transaction(function () use (
                $metadata, $companyId, $serviceId, $totalPaid,
                $customerEmail, $customerPhone, $stripePaymentStatus,
                $service, $user, $stripeSessionId, &$sale
            ) {
                // A. Buscar o registrar al cliente en org_customers vinculando su user_id
                $customerId = $this->resolveInvestorCustomer($companyId, $user, $customerEmail, $customerPhone);

                // B. Crear el Registro de la Venta
                $sale = OrgSale::create([
                    'org_company_id' => $companyId,
                    'org_customer_id' => $customerId,
                    'org_service_id' => $serviceId,
                    'source_type' => 'stripe_investor_portal', // Diferenciador
                    'source_id' => $stripeSessionId,
                    'customer_origin' => 'YEL Investor Portal',
                    'product_name' => $metadata['service_name'] ?? 'Servicio Investor',
                    'total_amount' => $totalPaid,
                    'payment_status' => $stripePaymentStatus,
                    'seller_id' => null, // Normalmente en portal investor no hay afiliado directo en la compra
                    'commission_status' => 'not_applicable',
                ]);

                // C. Creación de la Orden de Trabajo
                if ($stripePaymentStatus === 'paid' && $service) {
                    $this->createServiceOrder($sale, $service, $customerId, $companyId);
                }
            });

        } catch (\Exception $e) {
            Log::critical('🚨 ERROR AL GUARDAR LA VENTA DE INVESTOR EN LA BASE DE DATOS.', [
                'error' => $e->getMessage(),
                'session_id' => $stripeSessionId,
            ]);

            return;
        }

        // D. Notificaciones e Integraciones fuera de transacción
        try {
            if ($sale && $stripePaymentStatus === 'paid') {
                $this->dispatchNotifications($sale, $stripePaymentStatus, $user, $companyId);
                if ($service) {
                    $this->dispatchToGHL($sale, $service, $user, $customerPhone);
                }
            }
        } catch (\Exception $ex) {
            Log::error('⚠️ Error no crítico despachando notificaciones/GHL (Investor).', ['error' => $ex->getMessage()]);
        }
    }

    /**
     * Resuelve el cliente asegurando que esté enlazado al User ID de Laravel.
     */
    private function resolveInvestorCustomer(int $companyId, User $user, ?string $checkoutEmail, ?string $phone): int
    {
        // 1. Buscamos si ya existe el org_customer vinculado a este user_id
        $customer = OrgCustomer::where('org_company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();

        // 2. Si no lo encuentra por user_id, buscamos por el email (por si compró antes de tener cuenta)
        if (! $customer && $checkoutEmail) {
            $customer = OrgCustomer::where('org_company_id', $companyId)
                ->where('email', $checkoutEmail)
                ->first();

            // Si lo encontramos por email, le inyectamos su user_id de una vez para enlazarlo permanentemente
            if ($customer) {
                $customer->update(['user_id' => $user->id]);
            }
        }

        // 3. Si existe el customer, actualizamos su teléfono si Stripe nos dio uno nuevo
        if ($customer) {
            if (! empty($phone) && $customer->phone !== $phone) {
                $customer->update(['phone' => $phone]);
            }

            return $customer->id;
        }

        // 4. Si no existe en lo absoluto, lo creamos vinculado al $user->id
        $nameParts = explode(' ', trim($user->name), 2);

        $newCustomer = OrgCustomer::create([
            'org_company_id' => $companyId,
            'user_id' => $user->id, // ¡Aquí está la magia de tu migración!
            'first_name' => $nameParts[0] ?? 'Inversor',
            'last_name' => $nameParts[1] ?? null,
            'email' => $checkoutEmail ?? $user->email,
            'phone' => $phone,
        ]);

        return $newCustomer->id;
    }

    /**
     * Genera la instancia operativa de la orden heredando los responsables
     */
    private function createServiceOrder(OrgSale $sale, OrgService $service, int $customerId, int $companyId): void
    {
        $order = OrgServiceOrder::create([
            'org_company_id' => $companyId,
            'org_sale_id' => $sale->id,
            'org_service_id' => $service->id,
            'org_customer_id' => $customerId,
            'assigned_to' => $service->default_assignee_id,
            'status' => 'pending',
            'metadata' => [
                'initiated_by' => 'yel_investor_portal',
                'cloned_at' => now()->toDateTimeString(),
            ],
        ]);

        $defaultFollowerIds = $service->defaultFollowers()->pluck('users.id')->toArray();
        if (! empty($defaultFollowerIds)) {
            $order->followers()->sync($defaultFollowerIds);
        }

        Log::info("⚙️ Orden de Trabajo INVESTOR generada: UID {$order->uid}");
    }

    /**
     * Notificaciones (Igual que el normal, pero usa el modelo User en vez de variables sueltas)
     */
    private function dispatchNotifications(OrgSale $sale, string $status, User $user, int $companyId): void
    {
        Mail::to($user->email)->send(new ServicePurchaseSuccessMail($sale, $user->name));

        $company = OrgCompany::find($companyId);
        if ($company && $company->owner_id) {
            $adminUser = User::find($company->owner_id);
            if ($adminUser) {
                Mail::to($adminUser->email)->send(new InternalSaleNotificationMail($sale, $adminUser->name, 'Administrador'));
            }
        }
    }

    /**
     * GHL Dispatch
     */
    private function dispatchToGHL(OrgSale $sale, OrgService $service, User $user, ?string $customerPhone): void
    {
        $nameParts = explode(' ', trim($user->name), 2);

        $payload = [
            'first_name' => $nameParts[0] ?? 'Inversor',
            'last_name' => $nameParts[1] ?? '',
            'email' => $user->email,
            'phone' => $customerPhone,
            'service_purchased' => $sale->product_name,
            'service_id' => $service->id,
            'total_amount' => $sale->total_amount,
            'source' => 'yel_investor_checkout',
            'company_id' => $sale->org_company_id,
        ];

        \App\Jobs\SendSaleToGHLDispatcherJob::dispatch($payload);
    }
}
