<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgService;
use Illuminate\Http\Request;

class InvestorCheckoutController extends Controller
{
    public function createSession(string $companyUid, Request $request)
    {
        $request->validate([
            'service_uid' => 'required|exists:org_services,uid',
        ]);

        try {
            // 1. Validamos que la empresa exista
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            // 2. Obtenemos el usuario autenticado en Yel Investor
            $user = $request->user();

            // 3. Buscamos el servicio asegurándonos que pertenezca a ESTA empresa y esté activo
            $service = OrgService::where('uid', $request->service_uid)
                ->where('org_company_id', $company->id)
                ->where('is_active', true)
                ->firstOrFail();

            if (!$service->stripe_price_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este servicio no tiene un precio de Stripe configurado.',
                ], 400);
            }

            // 4. Aseguramos que el usuario tenga un customer ID en Stripe (vía Laravel Cashier)
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            $items = [$service->stripe_price_id => 1];

            // 5. Metadata específica para Yel Investor
            $metadata = [
                'service_uid' => $service->uid,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'company_id' => $company->id,
                'company_uid' => $company->uid,
                'user_id' => $user->id,
                'purchase_type' => 'yel_investor', 
            ];

            // Obtenemos la URL configurada para Yel Investor
            $investorUrl = config('app.yelinvestor_url', 'https://www.yelinvestor.com');

            // 6. Creamos la sesión usando el usuario autenticado (Cashier nativo)
            $checkout = $user->checkout($items, [
                'success_url' => "{$investorUrl}/dashboard/{$company->uid}/investor-services?success=true&session_id={CHECKOUT_SESSION_ID}",
                'cancel_url' => "{$investorUrl}/dashboard/{$company->uid}/investor-services?canceled=true",
                'metadata' => $metadata,
                'payment_intent_data' => ['metadata' => $metadata],
                'phone_number_collection' => [
                    'enabled' => true,
                ],
            ]);

            return response()->json([
                'success' => true,
                'url' => $checkout->url,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la sesión de pago: ' . $e->getMessage(),
            ], 500);
        }
    }
}