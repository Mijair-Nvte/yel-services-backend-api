<?php

namespace App\Http\Controllers\Api\Auth;

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
            // 💡 Lo hacemos 'nullable' para que sea universal
            'workspace_uid' => ['nullable', 'exists:org_companies,uid'], 
        ]);

        $user = User::findOrFail($request->user_id);
        $cacheKey = 'register_otp_'.$user->id;
        $cachedOtp = Cache::get($cacheKey);

        if (! $cachedOtp || $cachedOtp !== $request->otp) {
            return response()->json([
                'message' => 'El código de verificación es inválido o ha expirado.',
            ], 400);
        }

        // 🔒 1. El modo 100% oficial de Laravel
        if ($user->markEmailAsVerified()) {
            event(new Verified($user)); 
        }

        // 2. Eliminar el OTP de la caché por seguridad
        Cache::forget($cacheKey);

        // 3. Generar el Token de sesión final (¡Auto-login!)
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. Lógica de bienvenida separada por casos
        if ($request->filled('workspace_uid')) {
            // Caso Yel Pro / Partners (Traen workspace_uid)
            $company = OrgCompany::where('uid', $request->workspace_uid)->first();
            Mail::to($user->email)->send(new WelcomeAffiliateMail($user, $company));
        } else {
            // Caso App Móvil / Dashboard (Podrías poner un correo de bienvenida genérico aquí luego)
        }

        return response()->json([
            'message' => 'Correo verificado y sesión iniciada correctamente.',
            'user' => $user,
            'token' => $token,
        ], 200);
    }
}