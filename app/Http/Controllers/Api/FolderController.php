<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\OrgArea;
use App\Models\OrgCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FolderController extends Controller
{
    /**
     * 📂 Carpetas raíz (Company o Area)
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:company,area',
            'uid' => 'required|string',
        ]);

        if ($data['type'] === 'company') {
            $company = OrgCompany::where('uid', $data['uid'])->firstOrFail();

            return Folder::whereNull('parent_id')
                ->where('folderable_type', OrgCompany::class)
                ->where('folderable_id', $company->id)
                ->orderBy('name')
                ->get();
        }

        if ($data['type'] === 'area') {
            $area = OrgArea::where('uid', $data['uid'])->firstOrFail();

            return Folder::whereNull('parent_id')
                ->where('folderable_type', OrgArea::class)
                ->where('folderable_id', $area->id)
                ->orderBy('name')
                ->get();
        }
    }

    /**
     * 📁 Subcarpetas + documentos
     */
    public function children(Folder $folder)
    {
        return response()->json([
            'folders' => $folder->children()->orderBy('name')->get(),
            'documents' => $folder->documents()->latest()->get(),
        ]);
    }

    /**
     * 📂 Crear carpeta
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
            'company_uid' => 'nullable|exists:org_companies,uid',
            'area_uid' => 'nullable|exists:org_areas,uid',
        ]);

        if (! $data['company_uid'] && ! $data['area_uid']) {
            return response()->json([
                'message' => 'Debe pertenecer a una compañía o a un área',
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($data['company_uid']) {
                $company = OrgCompany::where('uid', $data['company_uid'])->firstOrFail();
                $folderable = $company;
            } else {
                $area = OrgArea::where('uid', $data['area_uid'])->firstOrFail();
                $folderable = $area;
            }

            $folder = Folder::create([
                'name' => $data['name'],
                'parent_id' => $data['parent_id'] ?? null,
                'folderable_id' => $folderable->id,
                'folderable_type' => get_class($folderable),
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json($folder, 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => 'Error al crear carpeta'], 500);
        }
    }

    // ✏️ Renombrar carpeta
    public function update(Request $request, Folder $folder)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $folder->update([
            'name' => $data['name'],
        ]);

        return response()->json($folder);
    }

    /**
     * 📁 Contenido de carpeta
     */
    public function show(Folder $folder)
    {
        return response()->json([
            'folder' => $folder,
            'folders' => $folder->children()->orderBy('order')->get(),
            'documents' => $folder->documents()->latest()->get(),
        ]);
    }

    /**
     * 🗑️ Eliminar carpeta recursivamente
     */
    public function destroy(Folder $folder)
    {
        DB::transaction(fn () => $this->deleteRecursive($folder));

        return response()->json([
            'message' => 'Carpeta eliminada correctamente',
        ]);
    }

    private function deleteRecursive(Folder $folder)
    {
        foreach ($folder->documents as $doc) {
            $doc->delete();
        }

        foreach ($folder->children as $child) {
            $this->deleteRecursive($child);
        }

        $folder->delete();
    }
}
