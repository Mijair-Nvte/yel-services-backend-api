<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\OrgCompany;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PartnerFolderController extends Controller
{
    /**
     * 📁 Obtener la Raíz de "Mis Archivos" (Carpetas raíz + Archivos sueltos en la raíz)
     */
    /**
     * 📁 Obtener la Raíz ("Mi Unidad")
     */
    public function index(Request $request, string $companyUid)
    {
        $user = Auth::user();
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        // 🌟 MAGIA AQUÍ: Buscamos "Mi Unidad". Si no existe para este usuario y empresa, la crea automáticamente.
        $miUnidad = Folder::firstOrCreate([
            'org_company_id' => $company->id,
            'folderable_type' => User::class,
            'folderable_id' => $user->id,
            'parent_id' => null, // Es la raíz absoluta real en la BD
            'name' => 'Mi Unidad',
        ], [
            'created_by' => $user->id,
            // Nota: el 'uid' se genera solo gracias a tu método booted() en el modelo Folder
        ]);

        // Devolvemos la carpeta "Mi Unidad" y todo lo que tiene DENTRO
        return response()->json([
            'folder' => $miUnidad, // Info de la unidad principal
            'folders' => $miUnidad->children()->orderBy('name')->get(), // Subcarpetas
            'documents' => $miUnidad->documents()->latest()->get(), // Archivos sueltos
        ]);
    }

    /**
     * 📂 Crear una carpeta privada para el usuario
     */
    public function store(Request $request, string $companyUid)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
        ]);

        $user = Auth::user();
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        // Si NO mandan parent_id, por defecto la metemos dentro de "Mi Unidad"
        $parentId = $data['parent_id'];
        if (empty($parentId)) {
            $miUnidad = Folder::firstOrCreate([
                'org_company_id' => $company->id,
                'folderable_type' => User::class,
                'folderable_id' => $user->id,
                'parent_id' => null,
                'name' => 'Mi Unidad',
            ], ['created_by' => $user->id]);

            $parentId = $miUnidad->id;
        } else {
            // Validamos que el parent_id enviado le pertenezca al usuario
            Folder::where('id', $parentId)
                ->where('folderable_type', User::class)
                ->where('folderable_id', $user->id)
                ->firstOrFail();
        }

        DB::beginTransaction();
        try {
            $folder = Folder::create([
                'org_company_id' => $company->id,
                'name' => $data['name'],
                'parent_id' => $parentId, // Ahora siempre tendrá un padre válido
                'folderable_id' => $user->id,
                'folderable_type' => User::class,
                'created_by' => $user->id,
            ]);

            DB::commit();

            return response()->json($folder, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al crear carpeta de partner: '.$e->getMessage());

            return response()->json(['error' => 'Error al crear la carpeta'], 500);
        }
    }

    /**
     * 📁 Ver contenido de una subcarpeta específica (Subcarpetas + Documentos internos)
     */
    public function show(string $companyUid, string $folderUid)
    {
        $user = Auth::user();
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        // Buscamos la carpeta asegurando que pertenezca al usuario autenticado
        $folder = Folder::where('uid', $folderUid)
            ->where('org_company_id', $company->id)
            ->where('folderable_type', User::class)
            ->where('folderable_id', $user->id)
            ->firstOrFail();

        return response()->json([
            'folder' => $folder,
            'folders' => $folder->children()->orderBy('name')->get(),
            'documents' => $folder->documents()->latest()->get(),
        ]);
    }

    /**
     * ✏️ Renombrar carpeta
     */
    public function update(Request $request, string $companyUid, string $folderUid)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $folder = Folder::where('uid', $folderUid)
            ->where('folderable_type', User::class)
            ->where('folderable_id', $user->id)
            ->firstOrFail();

        $folder->update(['name' => $data['name']]);

        return response()->json($folder);
    }

    /**
     * 🗑️ Eliminar carpeta de manera segura y recursiva
     */
    public function destroy(string $companyUid, string $folderUid)
    {
        $user = Auth::user();

        $folder = Folder::where('uid', $folderUid)
            ->where('folderable_type', User::class)
            ->where('folderable_id', $user->id)
            ->firstOrFail();

        try {
            DB::transaction(fn () => $this->deleteRecursive($folder));

            return response()->json(['message' => 'Carpeta y archivos eliminados correctamente']);
        } catch (\Throwable $e) {
            Log::error('Error eliminando carpeta privada', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Ocurrió un error al eliminar la carpeta.'], 500);
        }
    }

    private function deleteRecursive(Folder $folder)
    {
        foreach ($folder->documents as $doc) {
            if ($doc->storage_service && $doc->file_url) {
                try {
                    Storage::disk($doc->storage_service)->delete($doc->file_url);
                } catch (\Exception $e) {
                    Log::warning("No se pudo borrar archivo físico: {$doc->file_url}");
                }
            }
            $doc->delete();
        }

        foreach ($folder->children as $child) {
            $this->deleteRecursive($child);
        }

        $folder->delete();
    }
}
