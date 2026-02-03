<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyLink;
use Illuminate\Http\Request;

class OrgCompanyLinkController extends Controller
{
    // 📌 Listar links de una compañía
    public function index(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        return response()->json([
            'data' => $company->links()->latest()->get(),
        ]);
    }

    // 📌 Crear link
    public function store(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'description' => 'nullable|string',
        ]);

        $link = $company->links()->create($validated);

        return response()->json([
            'message' => 'Link creado correctamente',
            'data' => $link,
        ], 201);
    }

    // 📌 Mostrar un link
    public function show(string $uid)
    {
        $link = OrgCompanyLink::where('uid', $uid)->firstOrFail();

        return response()->json([
            'data' => $link,
        ]);
    }

    // 📌 Actualizar link
    public function update(Request $request, string $uid)
    {
        $link = OrgCompanyLink::where('uid', $uid)->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'url' => 'sometimes|required|url|max:500',
            'description' => 'nullable|string',
        ]);

        $link->update($validated);

        return response()->json([
            'message' => 'Link actualizado correctamente',
            'data' => $link,
        ]);
    }

    // 📌 Eliminar link
    public function destroy(string $uid)
    {
        $link = OrgCompanyLink::where('uid', $uid)->firstOrFail();

        $link->delete();

        return response()->json([
            'message' => 'Link eliminado correctamente',
        ]);
    }
}
