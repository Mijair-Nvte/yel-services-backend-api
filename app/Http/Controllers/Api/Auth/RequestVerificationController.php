<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\VerifyRegistrationOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RequestVerificationController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Correo no encontrado'], 404);
        }

        if (!is_null($user->email_verified_at)) {
            return response()->json(['message' => 'El correo ya está verificado.'], 400);
        }

        // Reutilizamos tu lógica de OTP de registro
        $otp = sprintf("%06d", mt_rand(1, 999999));
        Cache::put('register_otp_' . $user->id, $otp, now()->addMinutes(15));
        Mail::to($user->email)->send(new VerifyRegistrationOtpMail($otp, $user->name));

        return response()->json([
            'message' => 'Código de verificación enviado.',
            'user_id' => $user->id
        ]);
    }
}