<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    /**
     * 👤 Obtener perfil del usuario autenticado
     */
    public function show()
    {
        $user = Auth::user()->load('profile');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'profile' => [
                    'first_name' => optional($user->profile)->first_name,
                    'last_name'  => optional($user->profile)->last_name,
                    'phone'      => optional($user->profile)->phone,
                    'country'    => optional($user->profile)->country,
                    'state'      => optional($user->profile)->state,
                    'city'       => optional($user->profile)->city,
                    'avatar_url' => optional($user->profile)->avatar_url,
                    'timezone'   => optional($user->profile)->timezone,
                    'language'   => optional($user->profile)->language,
                ],
            ],
        ]);
    }

    /**
     * ✏️ Actualizar perfil
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'phone'      => 'nullable|string|max:30',
            'country'    => 'nullable|string|max:100',
            'state'      => 'nullable|string|max:100',
            'city'       => 'nullable|string|max:100',
        ]);

        $user->profile()->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'data' => [
                'profile' => $user->fresh()->profile,
            ],
        ]);
    }

    /**
     * 🖼 Subir avatar
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $user = Auth::user();

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->profile()->update([
            'avatar' => $path,
        ]);

        return response()->json([
            'message' => 'Avatar actualizado correctamente',
            'avatar_url' => $user->fresh()->profile->avatar_url,
        ]);
    }
}
