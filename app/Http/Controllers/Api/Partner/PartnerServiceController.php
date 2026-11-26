<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Mail\ShareServiceMail;
use App\Models\OrgCompany;
use App\Models\OrgCompanyPartner;
use App\Models\OrgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Checkout;

class PartnerServiceController extends Controller
{
    public function index(string $companyUid, Request $request): JsonResponse
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            // 1. Buscamos el registro de afiliado y validamos que su estatus sea 'approved'
            $partner = OrgCompanyPartner::where('org_company_id', $company->id)
                ->where('user_id', $request->user()->id)
                ->where('status', 'approved') // <--- EL CAMBIO CLAVE ESTÁ AQUÍ
                ->firstOrFail();

            // 2. Obtenemos los servicios activos de la empresa
            $services = OrgService::where('org_company_id', $company->id)
                ->where('is_active', true)
                ->select('uid', 'name', 'description', 'price', 'cover_image', 'availability_type', 'available_states')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'referral_code' => $partner->referral_code,
                'services' => $services,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar el catálogo de servicios.',
            ], 500);
        }
    }

    public function sendViaEmail(Request $request, string $companyUid, string $serviceUid)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'message' => 'nullable|string|max:1000',
            'referral_code' => 'required|string', // Lo recibimos del frontend
        ]);

        $user = Auth::user();

        // Validamos que el servicio exista y esté activo
        $service = OrgService::where('uid', $serviceUid)->firstOrFail();

        $senderName = $user->name ?? 'Un representante de YEL';

        // Construimos la URL hardcodeando el dominio correcto (yelpro.vip) con el código de afiliado
        $serviceUrl = "https://www.yelpro.vip/store/{$companyUid}?ref={$validated['referral_code']}&service={$serviceUid}";

        // Mandamos a cola
        Mail::to($validated['email'])->send(
            new ShareServiceMail(
                $service->name,
                $serviceUrl,
                $senderName,
                $validated['message']
            )
        );

        return response()->json([
            'success' => true,
            'message' => 'El enlace del servicio ha sido enviado al cliente.',
        ]);
    }

    /**
     * Genera una sesión de Stripe Checkout desde el dashboard del partner.
     * El referral_code se toma del partner autenticado (nunca del frontend).
     */
    public function checkout(Request $request, string $companyUid, string $serviceUid): JsonResponse
    {
        $validated = $request->validate([
            'customer_state' => 'required|string|max:100',
            'customer_email' => 'nullable|email',
        ]);

        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        // 1. Partner aprobado con código de referido
        $partner = OrgCompanyPartner::where('org_company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'approved')
            ->whereNotNull('referral_code')
            ->first();

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta de partner no está habilitada para realizar cobros.',
            ], 403);
        }

        // 2. Servicio activo y de ESTA empresa
        $service = OrgService::where('uid', $serviceUid)
            ->where('org_company_id', $company->id)
            ->where('is_active', true)
            ->first();

        if (! $service || empty($service->stripe_price_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Este servicio no está disponible para cobro.',
            ], 404);
        }

        // 3. Misma regla que la tienda: "restricted" = lista negra de estados
        $blockedStates = is_array($service->available_states) ? $service->available_states : [];

        if ($service->availability_type === 'restricted' && in_array($validated['customer_state'], $blockedStates, true)) {
            return response()->json([
                'success' => false,
                'message' => "Este servicio no está disponible en {$validated['customer_state']}.",
            ], 422);
        }

        // 4. Misma metadata que la tienda pública + datos del canal
        $metadata = [
            'type' => 'service_purchase',
            'service_uid' => $service->uid,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'referral_code' => $partner->referral_code,
            'company_id' => $company->id,
            'company_uid' => $company->uid,
            'channel' => 'partner_dashboard',
            'assisted_by_user_id' => (string) $request->user()->id,
            'customer_state' => $validated['customer_state'],
        ];

        $yelProUrl = config('app.yelpro_url');

        $sessionOptions = [
            'success_url' => $yelProUrl."/dashboard/{$company->uid}/partners?payment=success&session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => $yelProUrl."/dashboard/{$company->uid}/partners/services?payment=canceled",
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
            'phone_number_collection' => ['enabled' => true],
            'allow_promotion_codes' => true,
        ];

        if (! empty($validated['customer_email'])) {
            $sessionOptions['customer_email'] = $validated['customer_email'];
        }

        try {
            $checkout = Checkout::guest()->create([$service->stripe_price_id => 1], $sessionOptions);

            return response()->json([
                'success' => true,
                'url' => $checkout->url,
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar la sesión de pago.',
            ], 500);
        }
    }
}
