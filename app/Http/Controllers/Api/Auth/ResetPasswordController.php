<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'otp'     => ['required', 'numeric'],
            'password'=> ['required', 'min:8', 'confirmed'], 
        ]);

        $cacheKey = 'password_reset_otp_' . $request->user_id;
        $cachedOtp = Cache::get($cacheKey);

        // 1. Validar OTP
        if (!$cachedOtp || (int)$request->otp !== (int)$cachedOtp) {
            throw ValidationException::withMessages([
                'otp' => ['El código es incorrecto o ha expirado.'],
            ]);
        }

        // 2. Actualizar contraseña
        $user = User::findOrFail($request->user_id);
        $user->password = Hash::make($request->password);
        $user->save();

        // 3. Limpiar caché
        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.'
        ]);
    }
}