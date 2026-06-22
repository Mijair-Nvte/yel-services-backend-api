<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeAffiliateMail;
use App\Models\OrgCompany;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Verified;
class VerifyRegistrationController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'otp' => ['required', 'string', 'size:6'],
            'workspace_uid' => ['required', 'exists:org_companies,uid'],
        ]);

        $user = User::findOrFail($request->user_id);
        $cacheKey = 'register_otp_'.$user->id;
        $cachedOtp = Cache::get($cacheKey);

    if (! $cachedOtp || $cachedOtp !== $request->otp) {
            return response()->json([
                'message' => 'El código de verificación es inválido o ha expirado.',
            ], 400);
        }

        // 🔒 2. El modo 100% oficial de Laravel
        // markEmailAsVerified() actualiza la base de datos y retorna true si fue exitoso
        if ($user->markEmailAsVerified()) {
            // Disparamos el evento nativo para que el framework reaccione
            event(new Verified($user)); 
        }

        // 3. Eliminar el OTP de la caché por seguridad
        Cache::forget($cacheKey);

        // 4. Generar el Token de sesión final
        $token = $user->createToken('auth_token')->plainTextToken;

        // 5. (Opcional pero recomendado) Enviar el correo de bienvenida AHORA que es un usuario real
        $company = OrgCompany::where('uid', $request->workspace_uid)->first();
        Mail::to($user->email)->send(new WelcomeAffiliateMail($user, $company));

        return response()->json([
            'message' => 'Correo verificado y sesión iniciada correctamente.',
            'user' => $user,
            'token' => $token,
        ], 200);
    }
}
