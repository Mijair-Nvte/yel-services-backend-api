<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrgCompany;
use App\Models\OrgModuleSetting;
use App\Jobs\GoHighLevel\FetchGhlContactsJob;

class GhlSyncController extends Controller
{
    public function startSync(Request $request, $uid)
    {
        // 1. Buscamos la compañía real usando el UID de la URL
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $companyId = $company->id;
        
        // 2. Buscamos o creamos el módulo de integraciones para esa compañía
        $settings = OrgModuleSetting::firstOrCreate(
            ['org_company_id' => $companyId, 'module_name' => 'crm_integrations'],
            ['settings' => []]
        );

        $config = $settings->settings ?? [];

        // 3. Validamos si ya se corrió antes
        if (isset($config['initial_sync_completed']) && $config['initial_sync_completed'] === true) {
            return response()->json([
                'error' => true,
                'message' => 'La sincronización histórica ya fue realizada previamente para esta empresa.'
            ], 400);
        }

        // 4. Marcamos como completada y guardamos
        $config['initial_sync_completed'] = true;
        $settings->settings = $config;
        $settings->save();

        // 5. Despachamos el Job con el ID dinámico
        FetchGhlContactsJob::dispatch($companyId, null);

        return response()->json([
            'success' => true,
            'message' => 'Sincronización masiva en segundo plano iniciada con éxito.'
        ]);
    }
}