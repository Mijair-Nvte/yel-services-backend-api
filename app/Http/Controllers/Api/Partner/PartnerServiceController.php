<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyPartner;
use App\Models\OrgService;
use App\Mail\ShareServiceMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
                ->select('uid', 'name', 'description', 'price', 'cover_image')
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
}
