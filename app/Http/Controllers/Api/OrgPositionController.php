<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrgPositionController extends Controller
{
    public function index()
    {
        return response()->json(
            OrgPosition::orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);

        return response()->json(
            OrgPosition::create($data),
            201
        );
    }

    public function show($id)
    {
        return response()->json(
            OrgPosition::findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $position = OrgPosition::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $position->update($data);

        return response()->json($position);
    }

    public function destroy($id)
    {
        OrgPosition::findOrFail($id)->delete();

        return response()->json(['message' => 'Position deleted']);
    }
}
