<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\OrgService;
use App\Models\OrgCompany;
use Illuminate\Http\Request;
use Laravel\Cashier\Checkout;

class StripeCheckoutController extends Controller
{
    public function createSession(string $uid, Request $request)
    {
        $request->validate([
            'service_uid'   => 'required|exists:org_services,uid',
            'referral_code' => 'nullable|string',
        ]);

        // 1. Validamos que la empresa exista
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // 2. Buscamos el servicio asegurándonos que pertenezca a ESTA empresa
        $service = OrgService::where('uid', $request->service_uid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        $items = [ $service->stripe_price_id => 1 ];

        // 3. Metadata robusta para el Webhook
        $metadata = [
            'service_uid'   => $service->uid,
            'service_id'    => $service->id,
            'service_name'  => $service->name,
            'referral_code' => $request->referral_code,
            'company_id'    => $company->id, // ID interno para org_sales
            'company_uid'   => $company->uid,
        ];

        try {
            $checkout = Checkout::guest()->create($items, [
                'success_url' => config('app.frontend_url') . '/payment/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => config('app.frontend_url') . "/store/{$company->uid}?ref=" . ($request->referral_code ?? ''),
                'metadata'    => $metadata,
                'payment_intent_data' => [ 'metadata' => $metadata ],
            ]);

            return response()->json([
                'success' => true,
                'url'     => $checkout->url
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}