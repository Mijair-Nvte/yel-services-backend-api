<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\OrgCompany;
use App\Models\OrgPosition;
use Illuminate\Http\Request;

class OrgPositionController extends Controller
{
    use AuthorizesWorkspace;

    public function index(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        return response()->json(
            OrgPosition::where('org_company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $position = OrgPosition::create([
            'org_company_id' => $company->id,
            'name' => $data['name'],
            'slug' => \Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json($position, 201);
    }

    public function destroy(string $uid, int $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $position = OrgPosition::where('org_company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $position->delete();

        return response()->json(['message' => 'Position deleted']);
    }
}
