<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Mail\LoginSuccessfulMail; 

class VerifyOtpController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'otp' => ['required', 'numeric', 'digits:6'],
        ]);

        $cacheKey = 'login_otp_' . $request->user_id;
        $cachedOtp = Cache::get($cacheKey);

        // Verificamos si el código existe y coincide
        if (! $cachedOtp || (string) $cachedOtp !== (string) $request->otp) {
            throw ValidationException::withMessages([
                'otp' => ['El código es incorrecto o ha expirado.'],
            ]);
        }

        // Si es correcto, eliminamos el OTP de la caché para que no se pueda reusar
        Cache::forget($cacheKey);

        $user = User::find($request->user_id);

        // Creamos el token de acceso real
        $token = $user->createToken('auth_token')->plainTextToken;

        // Enviamos el correo de aviso de inicio de sesión exitoso
        // Es buena práctica pasar la IP y el User-Agent para mayor contexto
        Mail::to($user->email)->send(new LoginSuccessfulMail(
            $user->name,
            $request->ip(),
            $request->header('User-Agent')
        ));

        return response()->json([
            'message' => 'Login exitoso',
            'user' => $user,
            'token' => $token,
        ]);
    }
}