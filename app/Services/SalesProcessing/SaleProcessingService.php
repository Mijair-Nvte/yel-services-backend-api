<?php

namespace App\Services\SalesProcessing;

use App\Mail\Store\InternalSaleNotificationMail;
use App\Mail\Store\ServicePurchaseFailedMail;
use App\Mail\Store\ServicePurchaseSuccessMail;
use App\Models\OrgCompany;
use App\Models\OrgCompanyPartner;
use App\Models\OrgCustomer;
use App\Models\OrgSale;
use App\Models\OrgService;
use App\Models\OrgServiceOrder;
use App\Models\User;
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
        $serviceUid  = $metadata['service_uid'] ?? null;
        $totalPaid = $session['amount_total'] / 100;
        $serviceName = $metadata['service_name'] ?? null;
        $stripeSessionId = $session['id'] ?? null;

        if (! $companyId) {
            Log::error('❌ Error en SaleProcessingService: No se recibió el company_id en la metadata.', ['session_id' => $stripeSessionId]);

            return;
        }

        // Datos del cliente desde el evento
        $customerName = $session['customer_details']['name'] ?? 'Cliente Stripe';
        $customerEmail = $session['customer_details']['email'] ?? null;
        $customerPhone = $session['customer_details']['phone'] ?? null;

        $stripePaymentStatus = $session['payment_status'] === 'paid' ? 'paid' : 'pending';

        // Pre-cargamos el servicio
        $service = OrgService::find($serviceId);

        // Variables que necesitamos usar fuera de la transacción
        $sale = null;
        $sellerId = null;

        try {
            // -------------------------------------------------------------------------
            // 1. BLOQUE CRÍTICO (Transacción de Base de Datos)
            // Si algo falla aquí, hace rollback, pero SÓLO abarca la creación de registros
            // -------------------------------------------------------------------------
            DB::transaction(function () use (
                $metadata, $companyId, $serviceId, $totalPaid,
                $customerName, $customerEmail, $customerPhone, $stripePaymentStatus,
                $service, $stripeSessionId, &$sale, &$sellerId
            ) {
                // A. Buscar o registrar al cliente
                $customerId = $this->findOrCreateCustomer($companyId, $customerName, $customerEmail, $customerPhone);

                // B. Calcular Comisiones
                $commissionAmount = 0;
                $referralCode = $metadata['referral_code'] ?? null;

                if (! empty($referralCode) && $service) {
                    $partner = OrgCompanyPartner::with('tier')->where('referral_code', $referralCode)->first();

                    if ($partner) {
                        $sellerId = $partner->user_id;
                        $commissionPercentage = 8.00; // Default

                        if ($partner->tier && $partner->tier->commission_percentage > 0) {
                            $commissionPercentage = $partner->tier->commission_percentage;
                        }

                        $commissionAmount = $totalPaid * ($commissionPercentage / 100);

                        $newLifetimeVolume = $partner->lifetime_sales_volume + $totalPaid;
                        $partner->lifetime_sales_volume = $newLifetimeVolume;

                        $newTier = \App\Models\OrgPartnerTier::where('org_company_id', $companyId)
                            ->where('is_active', true)
                            ->where('min_sales_volume', '<=', $newLifetimeVolume)
                            ->orderBy('min_sales_volume', 'desc')
                            ->first();

                        if ($newTier && $newTier->id !== $partner->org_partner_tier_id) {
                            $partner->org_partner_tier_id = $newTier->id;
                            Log::info("🚀 ¡Level Up! Vendedor ID {$sellerId} subió al nivel {$newTier->name}");
                        }

                        $partner->save();
                    }
                }

                // C. Crear el Registro de la Venta
                $sale = OrgSale::create([
                    'org_company_id' => $companyId,
                    'org_customer_id' => $customerId,
                    'org_service_id' => $serviceId,
                    'source_type' => 'stripe_ecommerce',
                    'source_id' => $stripeSessionId,
                    'customer_origin' => 'Storefront Web',
                    'product_name' => $metadata['service_name'] ?? 'Servicio Profesional',
                    'total_amount' => $totalPaid,
                    'payment_status' => $stripePaymentStatus,
                    'seller_id' => $sellerId,
                    'referral_code' => $referralCode,
                    'commission_amount' => $commissionAmount,
                    'commission_status' => $sellerId ? 'pending' : 'not_applicable',
                ]);

                // D. Creación de la Orden de Trabajo
                if ($stripePaymentStatus === 'paid' && $service) {
                    $this->createServiceOrder($sale, $service, $customerId, $companyId);
                }
            });
            // <-- Fin de la transacción. Si llegamos aquí, los datos ESTÁN seguros en la BD.

        } catch (\Exception $e) {
            // Si la base de datos falla (ej. error de sintaxis SQL), logueamos el error grave
            // pero Stripe no sabrá que falló (porque Cashier devolverá 200 al terminar el listener)
            // Esto lo arreglaremos más adelante si decides no usar Cashier puro para esto,
            // pero al menos tenemos el log.
            Log::critical('🚨 ERROR AL GUARDAR LA VENTA EN LA BASE DE DATOS.', [
                'error' => $e->getMessage(),
                'session_id' => $stripeSessionId,
                'payload' => json_encode($session),
            ]);

            // Si la venta no se guardó, no tiene sentido intentar mandar mails o a GHL
            return;
        }

        // -------------------------------------------------------------------------
        // 2. BLOQUES SECUNDARIOS (Notificaciones e integraciones)
        // Están fuera de la transacción. Si fallan, no borran la venta de la BD.
        // -------------------------------------------------------------------------

        // Intentar despachar notificaciones (Mails)
        try {
            if ($sale) {
                $this->dispatchNotifications($sale, $stripePaymentStatus, $customerEmail, $customerName, $companyId, $sellerId);
            }
        } catch (\Exception $mailException) {
            // Si el correo falla (ej. error de Zoho SMTP), solo dejamos constancia en el log.
            // La venta y la orden YA ESTÁN creadas.
            Log::error('✉️ Error no crítico: Falló el envío de correos de la venta.', [
                'error' => $mailException->getMessage(),
                'sale_id' => $sale->id,
            ]);
        }

        // Intentar despachar a GoHighLevel
        try {
            if ($stripePaymentStatus === 'paid' && $service && $sale) {
                $this->dispatchToGHL($sale, $service, $customerName, $customerEmail, $customerPhone);
            }
        } catch (\Exception $ghlException) {
            // Si GHL está caído o el Job falla, igual que antes, solo logueamos.
            Log::error('🤖 Error no crítico: Falló el despacho a GoHighLevel.', [
                'error' => $ghlException->getMessage(),
                'sale_id' => $sale->id,
            ]);
        }
    }

    /**
     * Genera la instancia operativa de la orden heredando los responsables del catálogo
     */
    private function createServiceOrder(OrgSale $sale, OrgService $service, int $customerId, int $companyId): void
    {
        // Creamos la orden instanciando los datos actuales de la plantilla
        $order = OrgServiceOrder::create([
            'org_company_id' => $companyId,
            'org_sale_id' => $sale->id,
            'org_service_id' => $service->id,
            'org_customer_id' => $customerId,
            'assigned_to' => $service->default_assignee_id, // Hereda el Owner asignado por defecto
            'status' => 'pending',
            'metadata' => [
                'initiated_by' => 'stripe_webhook',
                'cloned_at' => now()->toDateTimeString(),
            ],
        ]);

        // Extraemos los IDs de los seguidores del catálogo original
        $defaultFollowerIds = $service->defaultFollowers()->pluck('users.id')->toArray();

        if (! empty($defaultFollowerIds)) {
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

        if (! empty($email)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)->where('email', $email)->first();
        }

        if (! $customer && ! empty($phone)) {
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
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
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

    /**
     * Prepara y envía los datos al webhook despachador de GoHighLevel
     */
    private function dispatchToGHL(OrgSale $sale, OrgService $service, string $customerName, ?string $customerEmail, ?string $customerPhone): void
    {
        // 1. Separar nombre y apellido si es necesario
        $nameParts = explode(' ', trim($customerName), 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? '';

        // 2. Obtener los nombres del Owner y Follower (para usarlos como Custom Fields en GHL)
        $ownerName = 'Equipo YEL';
        if ($service->default_assignee_id) {
            $owner = User::find($service->default_assignee_id);
            $ownerName = $owner ? $owner->name : 'Equipo YEL';
        }

        // Tomamos el primer follower como referencia (opcional)
        $followerName = 'No asignado';
        $followerUser = $service->defaultFollowers()->first();
        if ($followerUser) {
            $followerName = $followerUser->name;
        }

        // 3. Armar el payload estructurado
        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $customerEmail,
            'phone' => $customerPhone,

            // Campos personalizados para enrutar y personalizar en GHL
            'service_purchased' => $sale->product_name,
            'service_id' => $service->id, 
            'service_uid'      => $service->uid,
            'service_owner' => $ownerName,
            'service_follower' => $followerName,

            // Metadatos adicionales útiles
            'total_amount' => $sale->total_amount,
            'source' => 'stripe_checkout',
            'company_id' => $sale->org_company_id,
        ];

        // 4. Despachar el Job a la cola
        \App\Jobs\SendSaleToGHLDispatcherJob::dispatch($payload);
    }
}
