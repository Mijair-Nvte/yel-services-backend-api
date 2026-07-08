<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpPasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        // Candado de seguridad: Respondemos con éxito incluso si el correo no existe
        // para evitar ataques de enumeración de usuarios (saber quién está registrado).
        if (!$user) {
            return response()->json([
                'message' => 'Si el correo electrónico coincide con una cuenta, te hemos enviado un código OTP.'
            ]);
        }

        // Generamos un código OTP de 6 dígitos
        $otp = random_int(100000, 999999);

        // Lo guardamos en caché por 15 minutos usando el ID del usuario
        // Usamos un prefijo distinto al del login para evitar conflictos
        Cache::put('password_reset_otp_' . $user->id, $otp, now()->addMinutes(15));

        // Enviamos el correo en segundo plano
        Mail::to($user->email)->send(new OtpPasswordResetMail($otp, $user->name));

        return response()->json([
            'message' => 'Si el correo electrónico coincide con una cuenta, te hemos enviado un código OTP.',
            'require_otp' => true,
            'user_id' => $user->id, // Devolvemos el ID para que Next.js lo use en el siguiente paso
        ]);
    }
}