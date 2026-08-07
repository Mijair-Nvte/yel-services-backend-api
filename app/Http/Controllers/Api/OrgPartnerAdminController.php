<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyPartner;
use App\Services\Partner\PartnerService;
use Illuminate\Http\Request;

class OrgPartnerAdminController extends Controller
{
    use AuthorizesWorkspace;

    protected $partnerService;

    public function __construct(PartnerService $partnerService)
    {
        $this->partnerService = $partnerService;
    }

    /**
     * 📋 Listar partners de la compañía (con opción de filtrar por estatus)
     */
    public function index(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company); // Validar acceso al workspace

            // Permitir filtrar por estatus opcionalmente
            $status = $request->query('status');

            $query = OrgCompanyPartner::where('org_company_id', $company->id)
                ->with('user:id,name,email');

            // Solo filtramos si viene un estatus y no está vacío
            if (! empty($status)) {
                $query->where('status', $status);
            }

            // Ordenar para ver los más recientes primero
            $partners = $query->latest()->get();

            return response()->json($partners, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar los partners.'], 500);
        }
    }

    /**
     * 🔍 Ver detalle de una solicitud de partner específica
     */
    public function show(string $uid, $partnerId)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            $partner = OrgCompanyPartner::where('org_company_id', $company->id)
                ->where('id', $partnerId)
                ->with('user')
                ->firstOrFail();

            return response()->json($partner, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Solicitud no encontrada.'], 404);
        }
    }

    /**
     * ✅ Aprobar a un partner (Genera su código de referido)
     */
    public function approve(Request $request, string $uid, $partnerId)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            $partner = OrgCompanyPartner::where('org_company_id', $company->id)
                ->where('id', $partnerId)
                ->with('user') // Requerido por PartnerService para generar el código
                ->firstOrFail();

            // Usamos tu servicio existente
            $approvedPartner = $this->partnerService->approvePartner($partner);

            return response()->json([
                'success' => true,
                'message' => 'Partner aprobado exitosamente. Se ha generado su código de referido.',
                'data' => $approvedPartner,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * ❌ Rechazar la solicitud de un partner
     */
    public function reject(Request $request, string $uid, $partnerId)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            $partner = OrgCompanyPartner::where('org_company_id', $company->id)
                ->where('id', $partnerId)
                ->firstOrFail();

            // Usamos tu servicio existente
            $rejectedPartner = $this->partnerService->rejectPartner($partner);

            return response()->json([
                'success' => true,
                'message' => 'La solicitud del partner ha sido rechazada.',
                'data' => $rejectedPartner,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
