<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Auth;

class OrgCompanyController extends Controller
{
    use AuthorizesWorkspace;

    public function index()
    {
        return response()->json(
            OrgCompany::orderBy('name')->get()
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

        $data['slug'] = Str::slug($data['name']);

        $company = OrgCompany::create($data);

        OrgCompanyUser::create([
            'user_id' => Auth::id(),
            'org_company_id' => $company->id,
            'role' => 'owner',
        ]);

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
