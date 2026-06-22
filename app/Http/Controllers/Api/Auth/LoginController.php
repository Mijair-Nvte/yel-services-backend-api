<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpLoginMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

// ✅ Importamos el correo de registro

class LoginController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        // 1. Validamos las credenciales
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas'],
            ]);
        }

        // 🔒 2. CANDADO DE SEGURIDAD: Verificar si el correo está confirmado
        if (is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Tu correo no está verificado. Por favor, completa la verificación.',
                'needs_verification' => true,
            ], 403);
        }
        // 3. Flujo normal de Login (Solo llega aquí si ya verificó su correo)

        // Generamos un código OTP de 6 dígitos para LOGIN
        $otp = random_int(100000, 999999);

        // Lo guardamos en caché por 10 minutos
        Cache::put('login_otp_'.$user->id, $otp, now()->addMinutes(10));

        // Enviamos el correo con el código OTP de LOGIN
        Mail::to($user->email)->send(new OtpLoginMail($otp, $user->name));

        return response()->json([
            'message' => 'Código de acceso enviado a tu correo.',
            'require_otp' => true,
            'user_id' => $user->id,
        ]);
    }
}
