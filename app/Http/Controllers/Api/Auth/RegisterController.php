<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyRegistrationOtpMail;
use App\Models\OrgCompany;
use App\Models\OrgCustomer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

            // La contraseña la eligió el propio usuario (no es una cuenta importada)
            $user->forceFill(['password_set_at' => now()])->save();

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
                    // Alumnos / clientes: solo ficha en el CRM.
                    // Sin org_company_users ni roles: su acceso lo definen sus inscripciones.
                    $this->linkCustomerToCompany($user, $firstName, $lastName, $request->company_uid);
                    break;
                case 'dashboard':
                case 'yel_pro':
                case 'yel_investor':
                    break;
            }

            // Generamos el OTP de 6 dígitos
            $otp = sprintf('%06d', mt_rand(1, 999999));
            Cache::put('register_otp_'.$user->id, $otp, now()->addMinutes(15));
            Mail::to($user->email)->send(new VerifyRegistrationOtpMail($otp, $user->name));

            DB::commit();

            return response()->json([
                'message' => 'Cuenta creada. Por favor verifica tu correo.',
                'requiresOtp' => true,
                'user_id' => $user->id,
                'email' => $user->email,
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Ocurrió un error al registrar: '.$e->getMessage()], 500);
        }
    }

    /**
     * Vincula (o crea) la ficha del cliente en el CRM de la empresa.
     * Los alumnos de la app y la web de cursos NO se agregan a org_company_users
     * ni reciben roles de Spatie.
     */
    private function linkCustomerToCompany(User $user, string $firstName, ?string $lastName, string $companyUid): void
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $customer = OrgCustomer::withTrashed()
            ->where('org_company_id', $company->id)
            ->where('email', $user->email)
            ->first();

        if ($customer) {
            if ($customer->trashed()) {
                $customer->restore();
            }

            $customer->update([
                'user_id' => $customer->user_id ?? $user->id,
                'first_name' => $customer->first_name ?: $firstName,
                'last_name' => $customer->last_name ?: $lastName,
            ]);

            return;
        }

        // El modelo OrgCustomer genera el uid automáticamente
        OrgCustomer::create([
            'org_company_id' => $company->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }
}