<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgModuleSetting;
use App\Services\Meta\MetaApiService;
use Illuminate\Http\Request;

class MetaIntegrationController extends Controller
{
    protected $metaService;

    public function __construct(MetaApiService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Lista las páginas de Facebook conectadas a la empresa.
     */
    public function getPages($companyUid)
    {
        // 1. Buscamos la compañía por su UID
        $company = OrgCompany::where('uid', $companyUid)->first();

        if (!$company) {
            return response()->json(['message' => 'Empresa no encontrada.'], 404);
        }

        // 2. Buscamos el token del usuario en las configuraciones usando el ID interno
        $setting = OrgModuleSetting::where('org_company_id', $company->id)
            ->where('module_name', 'crm_integrations')
            ->first();

        $userToken = $setting->settings['meta']['access_token'] ?? null;

        if (!$userToken) {
            return response()->json(['message' => 'No hay conexión activa con Meta.'], 403);
        }

        // 3. Usamos el servicio para traer las páginas
        try {
            $pages = $this->metaService->getUserPages($userToken);
            return response()->json(['pages' => $pages]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}