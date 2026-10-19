<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCustomer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail; 
use Illuminate\Support\Facades\Cache; 
use App\Mail\VerifyRegistrationOtpMail; 
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'source' => ['required', 'in:dashboard,yel_mobile,yel_pro,yel_investor,yel_courses'],
            'company_uid' => ['nullable', 'string', 'required_if:source,yel_mobile,yel_courses'],
        ]);

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $nameParts = explode(' ', $request->name);
            $firstName = $nameParts[0];
            $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;

            $user->profile()->update([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);

            switch ($request->source) {
                case 'yel_mobile':
                case 'yel_courses':
                    $this->linkUserToCompany($user, $firstName, $lastName, $request->company_uid);
                    break;
                case 'dashboard':
                case 'yel_pro':
                case 'yel_investor':
                    break;
            }

            // Generamos el OTP de 6 dígitos
            $otp = sprintf("%06d", mt_rand(1, 999999));
            Cache::put('register_otp_' . $user->id, $otp, now()->addMinutes(15));
            Mail::to($user->email)->send(new VerifyRegistrationOtpMail($otp, $user->name));

            DB::commit();

            return response()->json([
                'message' => 'Cuenta creada. Por favor verifica tu correo.',
                'requiresOtp' => true,
                'user_id' => $user->id,
                'email' => $user->email
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Ocurrió un error al registrar: '.$e->getMessage()], 500);
        }
    }

  private function linkUserToCompany(User $user, $firstName, $lastName, $companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        // 1. Vincular o crear el registro en la tabla de clientes (org_customers)
        $customer = OrgCustomer::where('org_company_id', $company->id)->where('email', $user->email)->first();

        if ($customer) {
            $customer->update([
                'user_id' => $user->id,
                'first_name' => $customer->first_name ?? $firstName,
                'last_name' => $customer->last_name ?? $lastName,
            ]);
        } else {
            OrgCustomer::create([
                'uid' => 'cus_'.Str::random(16),
                'org_company_id' => $company->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);
        }

        // 2. Vincular al usuario con el Workspace en la tabla pivot org_company_users
        DB::table('org_company_users')->updateOrInsert(
            [
                'user_id' => $user->id,
                'org_company_id' => $company->id,
            ],
            [
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 3. ✅ Asignar el rol de Spatie usando el helper global de equipos de Spatie
        try {
            // Establecemos el ID del team/company para Spatie
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($company->id);
            
            if (!$user->hasRole('partner')) {
                $user->assignRole('partner');
            }
        } catch (\Exception $e) {
            // Fallback por si acaso tu versión de Spatie maneja los teams de otra forma
            // Aseguramos que al menos se le asigne el rol
            $user->assignRole('partner');
        }
    }
}