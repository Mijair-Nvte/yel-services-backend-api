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
            'source' => ['required', 'in:dashboard,yel_mobile,yel_pro,yel_investor'],
            'company_uid' => ['nullable', 'string', 'required_if:source,yel_mobile'],
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
                    $this->handleMobileRegistration($user, $firstName, $lastName, $request->company_uid);
                    break;
                case 'dashboard':
                case 'yel_pro':
                case 'yel_investor':
                    break;
            }

            // Generamos el OTP de 6 dígitos idéntico a Yel Pro
            $otp = sprintf("%06d", mt_rand(1, 999999));
            Cache::put('register_otp_' . $user->id, $otp, now()->addMinutes(15));
            Mail::to($user->email)->send(new VerifyRegistrationOtpMail($otp, $user->name));

            DB::commit();

            // Ya no retornamos el token Sanctum, bloqueamos hasta verificar
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

    private function handleMobileRegistration(User $user, $firstName, $lastName, $companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
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
    }
}