<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AffiliateMeController extends Controller
{
    public function __invoke(Request $request)
    {
        // 1. Cargamos el usuario con su perfil y la relación 'companies'
        // IMPORTANTE: También cargamos la relación anidada 'company' que está dentro de OrgCompanyUser
        $user = $request->user()->load(['profile', 'companies.company']);

        // 2. Obtenemos el primer registro de la tabla intermedia
        $pivot = $user->companies->first();

        // 3. El UID realmente vive dentro del modelo 'company' que está relacionado al pivot
        $workspaceUid = $pivot && $pivot->company ? $pivot->company->uid : null;

        // 4. Mandamos llamar a tu accessor. Esto devolverá el nivel o null si no tiene propiedades
        $tier = $user->current_investor_tier;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->profile->avatar_url ?? null,
                'workspace_uid' => $workspaceUid,
                'role' => 'partner',
                'tier' => $tier ? [
                    'name' => $tier->name,
                    'color' => $tier->color_theme ?? 'gray',
                ] : null,
            ],
        ]);
    }
}
