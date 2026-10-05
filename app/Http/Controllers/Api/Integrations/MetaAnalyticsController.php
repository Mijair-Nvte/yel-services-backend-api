<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgModuleSetting;
use App\Services\Meta\MetaApiService;
use Illuminate\Http\Request;

class MetaAnalyticsController extends Controller
{
    protected $metaService;

    public function __construct(MetaApiService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Obtiene la analítica completa (perfil, métricas globales y posts) de la página seleccionada.
     */
    public function getPostsAndReels($companyUid)
    {
        // 1. Buscamos la compañía
        $company = OrgCompany::where('uid', $companyUid)->first();

        if (!$company) {
            return response()->json(['message' => 'Empresa no encontrada.'], 404);
        }

        // 2. Obtenemos las configuraciones guardadas del módulo
        $setting = OrgModuleSetting::where('org_company_id', $company->id)
            ->where('module_name', 'crm_integrations')
            ->first();

        $metaConfig = $setting->settings['meta'] ?? null;
        $pageId = $metaConfig['selected_page_id'] ?? null;
        $pageAccessToken = $metaConfig['page_access_token'] ?? null;

        if (!$pageId || !$pageAccessToken) {
            return response()->json(['message' => 'No hay ninguna página de Meta seleccionada para esta empresa.'], 400);
        }

        // 3. Consultamos el servicio actualizado de Meta
        try {
            $analyticsData = $this->metaService->getFullPageAnalytics($pageId, $pageAccessToken);
            
            return response()->json($analyticsData);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    /**
     * Obtiene datos demográficos y geográficos de la audiencia.
     */
    public function getAudienceDemographics($companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->first();
        if (!$company) {
            return response()->json(['message' => 'Empresa no encontrada.'], 404);
        }

        $setting = OrgModuleSetting::where('org_company_id', $company->id)
            ->where('module_name', 'crm_integrations')
            ->first();

        $metaConfig = $setting->settings['meta'] ?? null;
        $pageId = $metaConfig['selected_page_id'] ?? null;
        $pageAccessToken = $metaConfig['page_access_token'] ?? null;

        if (!$pageId || !$pageAccessToken) {
            return response()->json(['message' => 'Configuración de Meta incompleta.'], 400);
        }

        try {
            $demographics = $this->metaService->getPageAudienceDemographics($pageId, $pageAccessToken);
            return response()->json($demographics);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}