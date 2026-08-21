<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OrgCompany;
use App\Models\OrgCompanyUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail; 
use Illuminate\Support\Facades\Cache; 
use App\Mail\VerifyRegistrationOtpMail; 
use App\Services\Partner\PartnerService;
class InvestorRegisterController extends Controller
{


    public function __construct(PartnerService $partnerService)
    {
        $this->partnerService = $partnerService;
    }


    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'workspace_uid' => ['required', 'exists:org_companies,uid'],
            'invite_code' => ['required', 'string'],
        ]);

        // ==========================================
        // 2. VALIDACIÓN HARDCODEADA DEL CÓDIGO
        // ==========================================
        $secretCode = 'YELINVESTOR26'; 

        // Validamos convirtiendo a mayúsculas para evitar errores de tipeo del usuario
        if (strtoupper(trim($validated['invite_code'])) !== $secretCode) {
            return response()->json([
                'message' => 'El código de invitación es inválido o ha expirado.'
            ], 400); // 400 Bad Request
        }


        try {
            return DB::transaction(function () use ($validated) {
                $company = OrgCompany::where('uid', $validated['workspace_uid'])->first();

                // 1. Crear el usuario (email_verified_at queda NULL por defecto)
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);

                // 2. Vincular a la empresa
                OrgCompanyUser::create([
                    'org_company_id' => $company->id,
                    'user_id' => $user->id,
                ]);

                // 3. Asignar rol (Ajustado a 'partner' según tu esquema de DB)
                setPermissionsTeamId($company->id);
                $user->assignRole('partner');

              $this->partnerService->joinProgram($user, $company);

                // 4. Generar OTP de 6 dígitos
                $otp = sprintf("%06d", mt_rand(1, 999999));
                
                // Guardar en Cache por 15 minutos (Altamente escalable)
                Cache::put('register_otp_' . $user->id, $otp, now()->addMinutes(15));

                // 5. Enviar correo a la COLA (No bloquea la petición)
                Mail::to($user->email)->send(new VerifyRegistrationOtpMail($otp, $user->name));

                // 6. Retornar respuesta indicando que requiere OTP (Sin token de acceso aún)
                return response()->json([
                    'message' => 'Cuenta creada. Por favor verifica tu correo.',
                    'requiresOtp' => true,
                    'user_id' => $user->id,
                    'email' => $user->email // Útil para mostrar en el frontend
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al registrar: '.$e->getMessage()], 500);
        }
    }
}