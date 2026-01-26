<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgArea;
use App\Models\OrgCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrgAreaController extends Controller
{
    use AuthorizesWorkspace;

    public function index(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        return response()->json(
            $company->areas()->orderBy('name')->get()
        );
    }

    public function store(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['org_company_id'] = $company->id;

        $area = OrgArea::create($data);

        return response()->json($area, 201);
    }

    public function show(string $uid)
    {
        $area = OrgArea::where('uid', $uid)
            ->with('company')
            ->firstOrFail();

        // Validación de workspace (recomendada)
        $this->authorizeWorkspace($area->company);

        return response()->json($area);
    }

    public function update(Request $request, $id)
    {
        $area = OrgArea::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $area->update($data);

        return response()->json($area);
    }

    public function destroy($id)
    {
        $area = OrgArea::findOrFail($id);
        $area->delete();

        return response()->json(['message' => 'Area deleted']);
    }
}
