<?php

namespace App\Http\Controllers\Api\Partner\Loan;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgLoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanApplicationController extends Controller
{
    /**
     * Listar todas las solicitudes de préstamo del partner actual.
     */
    public function index(Request $request, string $companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $userId = Auth::id();

        $applications = OrgLoanApplication::where('org_company_id', $company->id)
            ->where('user_id', $userId)
            ->latest()
            ->paginate(15); // O el número de paginación que uses por defecto

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
            'applicant_name'    => 'required|string|max:255',
            'applicant_email'   => 'required|email|max:255',
            'applicant_phone'   => 'required|string|max:30',
            'applicant_dob'     => 'required|date',
            'applicant_address' => 'required|string|max:255',
            'applicant_state'   => 'required|string|max:50',
            'loan_type'         => 'required|string|max:100',
            'estimated_amount'  => 'required|numeric|min:0',
            'notes'             => 'nullable|string',
        ]);

        // Agregamos las llaves foráneas y el estado inicial automáticamente
        $validated['org_company_id'] = $company->id;
        $validated['user_id'] = $userId;
        $validated['status'] = 'pending';

        $application = OrgLoanApplication::create($validated);

        return response()->json([
            'message' => 'Solicitud de préstamo enviada exitosamente.',
            'data'    => $application
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
        $application = OrgLoanApplication::where('org_company_id', $company->id)
            ->where('user_id', $userId)
            ->where('uid', $applicationUid)
            ->firstOrFail();

        return response()->json($application);
    }
}