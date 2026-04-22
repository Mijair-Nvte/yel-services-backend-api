<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrgCompanyController extends Controller
{
    use AuthorizesWorkspace;

    public function index()
    {
        return response()->json(
            OrgCompany::forUser(Auth::id())
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $user = Auth::user(); // Obtenemos al usuario autenticado
        $data['slug'] = Str::slug($data['name']);
        $data['owner_id'] = $user->id; // <--- Asignamos al dueño legal

        // 1. Creamos la compañía
        $company = OrgCompany::create($data);

        // 2. Lo vinculamos como miembro activo (sin el campo 'role')
        $company->users()->attach($user->id, ['is_active' => true]);

        // 3. Le damos el rol de 'admin' en Spatie dentro de ESTA empresa
        // Nota: Spatie con multitenancy requiere el ID de la empresa
        setPermissionsTeamId($company->id);
        $user->assignRole('admin');

        return response()->json($company, 201);
    }

    public function show(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $this->authorizeWorkspace($company);

        return response()->json($company);
    }

    public function update(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $this->authorizeWorkspace($company);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $company->update($data);

        return response()->json($company);
    }

    public function destroy($id)
    {
        $company = OrgCompany::findOrFail($id);
        $company->delete();

        return response()->json(['message' => 'Company deleted']);
    }
}
