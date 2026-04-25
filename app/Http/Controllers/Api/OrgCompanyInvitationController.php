<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyInvitation;
use App\Models\OrgCompanyUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrgCompanyInvitationController extends Controller
{
    use AuthorizesWorkspace;

    public function show(string $token)
    {
        $invite = OrgCompanyInvitation::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with(['company', 'area'])
            ->firstOrFail();

        return response()->json([
            'email' => $invite->email,
            'company' => $invite->company->name,
            'area' => $invite->area?->name,
            'role' => $invite->role,
        ]);
    }

    /**
     * 📩 Crear invitación a un workspace por correo
     */
    public function store(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // 🔐 Validar acceso al workspace
        $this->authorizeWorkspace($company);

        // ✅ Validación (Aceptamos el array de permisos)
        $data = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,member',
            'org_area_id' => 'nullable|exists:org_areas,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string'
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
         * ✅ 5. Guardar invitación (Incluyendo los permisos iniciales)
         */
        $invite = OrgCompanyInvitation::create([
            'org_company_id' => $company->id,
            'org_area_id' => $data['org_area_id'] ?? null,
            'email' => $data['email'],
            'role' => $data['role'],
            'permissions' => $data['permissions'] ?? [], // Guardamos el JSON
            'token' => $token,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invite->email)->send(
            new \App\Mail\OrgCompanyInvitationMail($invite)
        );

        return response()->json([
            'message' => 'Invitación creada correctamente',
            'invite' => $invite,
        ], 201);
    }

    public function accept(Request $request, string $token)
    {
        $invite = OrgCompanyInvitation::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|min:8|confirmed',
        ]);

        // Crear o buscar usuario
        $user = User::firstOrCreate(
            ['email' => $invite->email],
            [
                'name' => $data['name'],
                'password' => bcrypt($data['password']),
            ]
        );

        // 1. Agregar a la compañía (¡Sin la columna role!)
        OrgCompanyUser::firstOrCreate([
            'user_id' => $user->id,
            'org_company_id' => $invite->org_company_id,
        ], [
            'is_active' => true,
        ]);

        // 2. Asignar el rol y los permisos usando Spatie en el contexto de la empresa (Team)
        setPermissionsTeamId($invite->org_company_id);

        // Asignamos el rol ('admin' o 'member')
        $user->assignRole($invite->role);

        // 🔥 MAGIA: Asignar permisos específicos si es 'member' y trae permisos en la invitación
        if ($invite->role !== 'admin' && !empty($invite->permissions)) {
            $user->syncPermissions($invite->permissions);
        }

        // Marcar invitación como aceptada
        $invite->update([
            'accepted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Invitación aceptada correctamente',
        ]);
    }
}