<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyInvitation;
use App\Models\OrgCompanyUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrgCompanyInvitationController extends Controller
{
    use AuthorizesWorkspace;

    /**
     * 📩 Crear invitación a un workspace por correo
     */
    public function store(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // 🔐 Validar acceso al workspace
        $this->authorizeWorkspace($company);

        // ✅ Validación
        $data = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,member',
            'org_area_id' => 'nullable|exists:org_areas,id',
        ]);

        /**
         * ✅ 1. Verificar si el email ya existe como usuario
         */
        $user = User::where('email', $data['email'])->first();

        /**
         * ✅ 2. Si el usuario ya existe, verificar si ya pertenece a la compañía
         */
        if ($user) {
            $alreadyMember = OrgCompanyUser::where('org_company_id', $company->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyMember) {
                return response()->json([
                    'message' => 'Este usuario ya pertenece a la compañía',
                ], 409);
            }
        }

        /**
         * ✅ 3. Verificar si ya existe invitación pendiente
         */
        $alreadyInvited = OrgCompanyInvitation::where('org_company_id', $company->id)
            ->where('email', $data['email'])
            ->whereNull('accepted_at')
            ->exists();

        if ($alreadyInvited) {
            return response()->json([
                'message' => 'Ya existe una invitación pendiente para este correo',
            ], 409);
        }

        /**
         * ✅ 4. Crear token seguro
         */
        $token = Str::random(64);

        /**
         * ✅ 5. Guardar invitación
         */
        $invite = OrgCompanyInvitation::create([
            'org_company_id' => $company->id,
            'org_area_id' => $data['org_area_id'] ?? null,
            'email' => $data['email'],
            'role' => $data['role'],
            'token' => $token,
            'expires_at' => now()->addDays(7),
        ]);

        \Mail::to($invite->email)->send(
            new \App\Mail\OrgCompanyInvitationMail($invite)
        );

        return response()->json([
            'message' => 'Invitación creada correctamente',
            'invite' => $invite,
        ], 201);
    }
}
