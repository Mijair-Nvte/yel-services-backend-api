<?php

namespace App\Http\Controllers\Api\Partner\Insurance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\Insurance\StoreInsuranceApplicationRequest;
use App\Mail\InsuranceRequestMail;
use App\Models\OrgCompany;
use App\Models\OrgInsuranceApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail; // Lo crearemos en el siguiente paso

class InsuranceApplicationController extends Controller
{
    /**
     * 📋 Listar el historial de solicitudes de seguro del cliente.
     */
    public function index(Request $request, string $companyUid)
    {

        try {

            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            $applications = OrgInsuranceApplication::where('org_company_id', $company->id)

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
            $data['org_company_id'] = $company->id;
            $data['user_id'] = $user->id;
            $data['status'] = 'pending';

            $application = OrgInsuranceApplication::create($data);

            DB::commit();

            // ✉️ ENVÍO DE CORREO A DESTINATARIOS ESPECÍFICOS (EXCEPCIÓN TEMPORAL)
            try {
                // Definimos la lista de personas que deben recibir la notificación
                $destinatarios = [
                    'mnavarrete@yaestoylisto.com',
                    'operaciones@yel.com',
                    // 'kenneth@tuempresa.com', // Puedes agregar los correos exactos aquí
                ];

                // Laravel se encarga de enviar el correo a cada uno de la lista
                Mail::to($destinatarios)->send(new InsuranceRequestMail($application, $user));

            } catch (\Exception $e) {
                // Si falla el servidor de correo, se registra el error pero la app no se detiene
                Log::error('Error al enviar email de nuevo prospecto de seguro: '.$e->getMessage(), [
                    'application_uid' => $application->uid,
                ]);
            }

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

            // Verificamos que pertenezca a la empresa y al usuario actual

            $application = OrgInsuranceApplication::where('org_company_id', $company->id)

                ->where('user_id', $request->user()->id)

                ->where('uid', $applicationUid)

                ->firstOrFail();

            return response()->json(['data' => $application], 200);

        } catch (\Exception $e) {

            return response()->json(['message' => 'La solicitud de seguro no fue encontrada.'], 404);

        }

    }
}
