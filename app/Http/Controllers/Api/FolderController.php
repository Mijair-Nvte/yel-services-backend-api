<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\OrgArea;
use App\Models\OrgCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // <-- Agregué este import que faltaba en tu código
use Illuminate\Support\Facades\Storage;

class FolderController extends Controller
{
    /**
     * 📂 Carpetas raíz (Company o Area)
     */
    public function index(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $query = Folder::where('org_company_id', $company->id)
            ->whereNull('parent_id');

        $type = $request->query('type', 'company');

        if ($type === 'area') {
            $areaUid = $request->query('area_uid');
            $area = OrgArea::where('uid', $areaUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $query->where('folderable_type', OrgArea::class)
                ->where('folderable_id', $area->id);
        } else {
            $query->where('folderable_type', OrgCompany::class)
                ->where('folderable_id', $company->id);
        }

        return response()->json($query->orderBy('name')->get());
    }

    /**
     * 📁 Subcarpetas + documentos
     */
    // ✅ Agregamos string $uid como primer parámetro
    public function children(string $uid, Folder $folder)
    {
        // Opcional: Validar que la carpeta pertenece a esta compañía
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        if ($folder->org_company_id !== $company->id) {
            abort(403, 'No tienes acceso a esta carpeta.');
        }

        return response()->json([
            'folders' => $folder->children()->orderBy('name')->get(),
            'documents' => $folder->documents()->latest()->get(),
        ]);
    }

    /**
     * 📂 Crear carpeta
     */
    // ✅ Agregamos string $uid como primer parámetro
    public function store(Request $request, string $uid)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
            'area_uid' => 'nullable|exists:org_areas,uid',
        ]);

        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        DB::beginTransaction();

        try {
            // Si mandan un area_uid, pertenece al área. Si no, a la compañía.
            if (! empty($data['area_uid'])) {
                $area = OrgArea::where('uid', $data['area_uid'])
                    ->where('org_company_id', $company->id)
                    ->firstOrFail();
                $folderable = $area;
            } else {
                $folderable = $company;
            }

            // ✅ Aquí guardamos el org_company_id que acabas de agregar en la BD
            $folder = Folder::create([
                'org_company_id' => $company->id,
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
            Log::error('Error al crear carpeta: '.$e->getMessage());

            return response()->json(['error' => 'Error al crear carpeta'], 500);
        }
    }

    // ✏️ Renombrar carpeta
    // ✅ Agregamos string $uid como primer parámetro
    public function update(Request $request, string $uid, Folder $folder)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        if ($folder->org_company_id !== $company->id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $folder->update([
            'name' => $data['name'],
        ]);

        return response()->json($folder);
    }

    /**
     * 📁 Contenido de carpeta (Detalle)
     */
    // ✅ Agregamos string $uid como primer parámetro
    public function show(string $uid, Folder $folder)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        if ($folder->org_company_id !== $company->id) {
            abort(403);
        }

        return response()->json([
            'folder' => $folder,
            'folders' => $folder->children()->orderBy('order')->get(),
            'documents' => $folder->documents()->latest()->get(),
        ]);
    }

    /**
     * 🗑️ Eliminar carpeta recursivamente
     */
    // ✅ Agregamos string $uid como primer parámetro
    public function destroy(string $uid, Folder $folder)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        if ($folder->org_company_id !== $company->id) {
            abort(403);
        }

        try {
            DB::transaction(fn () => $this->deleteRecursive($folder));

            return response()->json([
                'message' => 'Carpeta eliminada correctamente',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error eliminando carpeta', ['error' => $e->getMessage(), 'folder_id' => $folder->id]);

            return response()->json(['message' => 'Ocurrió un error al eliminar la carpeta.'], 500);
        }
    }

    private function deleteRecursive(Folder $folder)
    {
        // 1. Iterar sobre todos los documentos de esta carpeta
        foreach ($folder->documents as $doc) {
            $disk = $doc->storage_service;
            $path = $doc->file_url;

            // Borrar físicamente de R2/S3 DIRECTAMENTE sin usar exists()
            if ($disk && $path) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (\Exception $e) {
                    Log::warning("No se pudo eliminar el archivo {$path} de Cloudflare al borrar la carpeta.", ['error' => $e->getMessage()]);
                }
            }

            // Borrar de la base de datos
            $doc->delete();
        }

        // 2. Si tiene subcarpetas, llamar a esta misma función de nuevo (Recursividad)
        foreach ($folder->children as $child) {
            $this->deleteRecursive($child);
        }

        // 3. Finalmente, borrar la carpeta actual
        $folder->delete();
    }

    /**
     * 🌐 Compartir carpeta en diferentes plataformas
     */
    public function compartir(Request $request, string $uid, string $folderUid)
    {
        // 1. Validar qué plataforma nos envían (ej: 'yel_pro', 'clientes', 'whatsapp')
        $data = $request->validate([
            'platform' => 'required|string|max:50',
        ]);

        // 2. Validar que la compañía existe
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // 3. Buscar la carpeta asegurando que pertenezca a la compañía
        $folder = Folder::where('uid', $folderUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        try {
            // 4. Obtenemos las plataformas actuales (si es null, lo iniciamos como array)
            $currentPlatforms = $folder->shared_platforms ?? [];

            // 5. Si la plataforma no está en el array, la agregamos y guardamos
            if (! in_array($data['platform'], $currentPlatforms)) {
                $currentPlatforms[] = $data['platform'];

                $folder->update([
                    'shared_platforms' => $currentPlatforms,
                ]);
            }

            // 6. Generamos la URL. (Ajusta la ruta según cómo funcione tu frontend)
            $shareUrl = url("/plataforma/carpeta-compartida/{$folder->uid}");

            return response()->json([
                'message' => 'Carpeta compartida con éxito.',
                'folder_name' => $folder->name,
                'shared_platforms' => $folder->shared_platforms,
                'share_url' => $shareUrl,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error al compartir carpeta', [
                'folder_uid' => $folderUid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo procesar la acción de compartir.',
            ], 500);
        }
    }
}
