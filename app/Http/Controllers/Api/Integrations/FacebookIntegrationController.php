<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use App\Models\OrgCompany;
use App\Models\OrgModuleSetting;

class FacebookIntegrationController extends Controller
{
    /**
     * Paso 1: Redirige al usuario a la pantalla de permisos de Facebook.
     * Ejemplo de petición desde el frontend: GET /api/v1/integrations/facebook/redirect?company_uid=XYZ123
     */
    public function redirectToFacebook(Request $request)
    {
        $companyUid = $request->query('company_uid');

        if (! $companyUid) {
            return response()->json(['message' => 'El company_uid es obligatorio'], 400);
        }

        // Generamos la URL oficial de Facebook pidiendo TODOS los permisos de analítica
        $redirectUrl = Socialite::driver('facebook')
            ->stateless()
            ->setScopes([
                // --- General / Ads ---
                'ads_read',
                'business_management', // Requerido muchas veces por Meta para leer cuentas de IG vinculadas a Pages
                
                // --- Permisos para Facebook Pages ---
                'pages_show_list',
                'pages_read_engagement', // Lee likes, comentarios, shares de Facebook
                'pages_read_user_content', // Lee el contenido multimedia (posts/videos) de Facebook
                
                // --- Permisos para Instagram ---
                'instagram_basic', // Lee el perfil y contenido multimedia de Instagram
                'instagram_manage_insights', // Lee las estadísticas avanzadas (alcance, interacciones en Reels, etc.)
            ])
            ->with(['state' => $companyUid])
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'url' => $redirectUrl,
        ]);
    }

    /**
     * Paso 2: Facebook redirige de vuelta aquí con el Token de Acceso.
     */
    public function handleFacebookCallback(Request $request)
    {
        try {
            // 1. Recuperamos el UID de la compañía que escondimos en el 'state'
            $companyUid = $request->input('state');

            // 2. Obtenemos los datos desde Facebook
            $facebookUser = Socialite::driver('facebook')->stateless()->user();
            $token = $facebookUser->token;
            $facebookName = $facebookUser->getName();

            // 3. Buscamos el ID interno de la compañía
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            // 4. Buscamos o creamos el registro en org_module_settings para 'crm_integrations'
            $moduleSetting = OrgModuleSetting::firstOrCreate(
                [
                    'org_company_id' => $company->id,
                    'module_name' => 'crm_integrations',
                ]
            );

            // 5. Extraemos el JSON actual (o un array vacío), inyectamos Meta, y guardamos
            $settings = $moduleSetting->settings ?? [];
            $settings['meta'] = [
                'access_token' => $token,
                'account_name' => $facebookName,
                'connected_at' => now()->toIso8601String(),
            ];

            $moduleSetting->settings = $settings;
            $moduleSetting->save();

            Log::info("Token de Meta guardado exitosamente para la empresa: {$companyUid}");

            // 6. Redirigimos al frontend con bandera de éxito
            $frontendDashboardUrl = env('FRONTEND_URL', 'http://localhost:3000')."/dashboard/{$companyUid}/settings?tab=integrations&meta_status=success";

            return redirect()->away($frontendDashboardUrl);

        } catch (\Exception $e) {
            Log::error('Error en el Callback de Facebook: '.$e->getMessage());

            $frontendErrorUrl = env('FRONTEND_URL', 'http://localhost:3000')."/dashboard/{$request->input('state')}/settings?tab=integrations&meta_status=error";

            return redirect()->away($frontendErrorUrl);
        }
    }
}