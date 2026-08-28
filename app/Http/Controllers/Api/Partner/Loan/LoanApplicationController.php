<?php

namespace App\Http\Controllers\Api\Partner\Loan;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgLoanApplication;
use App\Traits\HandlesCustomers;
use App\Traits\TriggersModuleAutomations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanApplicationController extends Controller
{
    use HandlesCustomers,TriggersModuleAutomations;

    /**
     * Listar todas las solicitudes de préstamo del partner actual.
     */
    public function index(Request $request, string $companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $userId = Auth::id();

        $applications = OrgLoanApplication::with('customer') 
            ->where('org_company_id', $company->id)
            ->where('user_id', $userId)
            ->latest()
            ->paginate(15);

        return response()->json($applications);
    }

    /**
     * Crear una nueva solicitud de préstamo.
     */
    public function store(Request $request, string $companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $userId = Auth::id();

        $validated = $request->validate([
            'applicant_name' => 'required|string|max:255',
            'applicant_email' => 'required|email|max:255',
            'applicant_phone' => 'required|string|max:30',
            'applicant_dob' => 'nullable|date',
            'applicant_address' => 'nullable|string|max:255',
            'applicant_state' => 'required|string|max:50',
            'loan_type' => 'required|string|max:100',
            'estimated_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // 3. Utilizamos el trait para buscar o crear al cliente centralizado
        $customerId = $this->findOrCreateCustomer(
            $company->id,
            $validated['applicant_name'],
            $validated['applicant_email'],
            $validated['applicant_phone']
        );

        // 4. Agregamos las llaves foráneas, el cliente y el estado inicial
        $validated['org_company_id'] = $company->id;
        $validated['user_id'] = $userId;
        $validated['org_customer_id'] = $customerId; // Vinculamos el ID del cliente devuelto por el trait
        $validated['status'] = 'Open';

        // Nota: commission_amount, commission_status y seller_payout_date
        // tomarán sus valores por defecto de la migración de forma automática.

        // Creamos la solicitud de préstamo de forma directa
        $application = OrgLoanApplication::create($validated);

        //Disparamos la automatización por el evento "created"
        $this->triggerAutomations($company, 'loans', 'created', $application);

        return response()->json([
            'message' => 'Solicitud de préstamo enviada exitosamente.',
            'data' => $application,
        ], 201);
    }

    /**
     * Ver el detalle de una solicitud específica del partner.
     */
    public function show(Request $request, string $companyUid, string $applicationUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $userId = Auth::id();

        // Buscamos la solicitud y validamos que pertenezca tanto a la compañía como al usuario actual
        $application = OrgLoanApplication::with('customer')
            ->where('org_company_id', $company->id)
            ->where('user_id', $userId)
            ->where('uid', $applicationUid)
            ->firstOrFail();

        return response()->json($application);
    }
}
