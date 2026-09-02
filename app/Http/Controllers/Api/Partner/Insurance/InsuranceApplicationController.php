<?php

namespace App\Http\Controllers\Api\Partner\Insurance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\Insurance\StoreInsuranceApplicationRequest;
use App\Models\OrgCompany;
use App\Models\OrgInsuranceApplication;
use App\Traits\HandlesCustomers;
use App\Traits\TriggersModuleAutomations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InsuranceApplicationController extends Controller
{
    use HandlesCustomers,TriggersModuleAutomations;

    /**
     * 📋 Listar el historial de solicitudes de seguro del cliente.
     */
    public function index(Request $request, string $companyUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            // Agregamos with('customer') para traer la relación
            $applications = OrgInsuranceApplication::with('customer')
                ->where('org_company_id', $company->id)
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['data' => $applications], 200);

        } catch (\Exception $e) {
            Log::error('Error al listar solicitudes de seguro: '.$e->getMessage());

            return response()->json(['message' => 'Error al listar las solicitudes de seguro.'], 500);
        }
    }

    /**
     * ➕ Procesar y almacenar una nueva solicitud de seguro.
     */
    public function store(StoreInsuranceApplicationRequest $request, string $companyUid)
    {
        DB::beginTransaction();

        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $user = $request->user();

            $data = $request->validated();

            // 1. Utilizamos el trait para buscar o crear al cliente centralizado
            $customerId = $this->findOrCreateCustomer(
                $company->id,
                $data['applicant_name'],
                $data['applicant_email'],
                $data['applicant_phone']
            );

            // 2. Agregamos las llaves foráneas y el cliente
            $data['org_company_id'] = $company->id;
            $data['user_id'] = $user->id;
            $data['org_customer_id'] = $customerId; // Vinculamos el ID del cliente
            $data['status'] = 'Open';

            $application = OrgInsuranceApplication::create($data);

             $application->load(['customer', 'user']);
             
            DB::commit();

            $this->triggerAutomations($company, 'insurances', 'created', $application);
            // Disparamos la automatización por el evento "created"

            return response()->json([
                'message' => 'Tu solicitud de revisión de seguro ha sido enviada correctamente.',
                'data' => $application,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fallo al crear la solicitud de seguro: '.$e->getMessage(), ['user_id' => auth()->id()]);

            return response()->json(['message' => 'Error interno al procesar tu solicitud. Inténtalo más tarde.'], 500);
        }
    }

    /**
     * 👁️ Ver detalles de una solicitud específica.
     */
    public function show(Request $request, string $companyUid, string $applicationUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            // Agregamos with('customer') para traer la relación
            $application = OrgInsuranceApplication::with('customer')
                ->where('org_company_id', $company->id)
                ->where('user_id', $request->user()->id)
                ->where('uid', $applicationUid)
                ->firstOrFail();

            return response()->json(['data' => $application], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'La solicitud de seguro no fue encontrada.'], 404);
        }
    }
}
