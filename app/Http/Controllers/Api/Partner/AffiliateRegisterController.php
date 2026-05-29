<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OrgCompany;
use App\Models\OrgCompanyUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AffiliateRegisterController extends Controller
{
    public function __invoke(Request $request)
    {
        // 1. Agregamos la validación del workspace_uid
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'workspace_uid' => ['required', 'exists:org_companies,uid'],
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                // 2. Buscamos la empresa a la que se va a afiliar
                $company = OrgCompany::where('uid', $validated['workspace_uid'])->first();

                // 3. Crear el usuario
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);

                // 4. Vincular el usuario a la empresa (Crucial para Spatie Multi-tenant)
                OrgCompanyUser::create([
                    'org_company_id' => $company->id,
                    'user_id' => $user->id,
                ]);

                // 5. Asignar el rol de 'affiliate' indicando a Spatie el ID de la empresa
                setPermissionsTeamId($company->id);
                $user->assignRole('affiliate');

                // 6. Generar token para el Frontend
                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'message' => 'Afiliado registrado correctamente en ' . $company->name,
                    'user' => $user,
                    'token' => $token,
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al registrar: '.$e->getMessage()], 500);
        }
    }
}