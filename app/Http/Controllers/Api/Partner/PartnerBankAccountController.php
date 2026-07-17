<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\OrgBankAccount;
use App\Models\OrgCompany;
use Illuminate\Http\Request;

class PartnerBankAccountController extends Controller
{
    /**
     * Listar las cuentas bancarias del usuario autenticado en esta compañía
     */
    public function index($companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $bankAccounts = OrgBankAccount::where('org_company_id', $company->id)
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bankAccounts,
        ]);
    }

    /**
     * Registrar una nueva cuenta bancaria
     */
    public function store(Request $request, $companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'clabe' => 'nullable|string|size:18',
        ]);

        // Validación manual: Se requiere al menos CLABE o Número de cuenta
        if (empty($validated['account_number']) && empty($validated['clabe'])) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'account_information' => ['Debes proporcionar la CLABE interbancaria o el número de cuenta.'],
                ],
            ], 422);
        }

        $bankAccount = OrgBankAccount::create([
            'org_company_id' => $company->id,
            'user_id' => auth()->id(),
            'bank_name' => $validated['bank_name'],
            'account_holder_name' => $validated['account_holder_name'],
            'account_number' => $validated['account_number'] ?? null,
            'clabe' => $validated['clabe'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta bancaria registrada correctamente.',
            'data' => $bankAccount,
        ], 201);
    }

    /**
     * Mostrar una cuenta bancaria específica
     */
    public function show($companyUid, $accountUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $bankAccount = OrgBankAccount::where('uid', $accountUid)
            ->where('org_company_id', $company->id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $bankAccount,
        ]);
    }

    /**
     * Actualizar una cuenta bancaria
     */
    public function update(Request $request, $companyUid, $accountUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $bankAccount = OrgBankAccount::where('uid', $accountUid)
            ->where('org_company_id', $company->id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated = $request->validate([
            'bank_name' => 'sometimes|required|string|max:255',
            'account_holder_name' => 'sometimes|required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'clabe' => 'nullable|string|size:18',
        ]);

        $bankAccount->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta bancaria actualizada correctamente.',
            'data' => $bankAccount,
        ]);
    }

    /**
     * Eliminar una cuenta bancaria (Soft Delete)
     */
    public function destroy($companyUid, $accountUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $bankAccount = OrgBankAccount::where('uid', $accountUid)
            ->where('org_company_id', $company->id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $bankAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cuenta bancaria eliminada correctamente.',
        ]);
    }
}
