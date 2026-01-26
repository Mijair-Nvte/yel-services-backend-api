<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * 📄 Listar documentos de una carpeta
     */
    public function byFolder(string $folderUid)
    {
        $folder = Folder::where('uid', $folderUid)->firstOrFail();

        return response()->json(
            $folder->documents()
                ->latest()
                ->get()
        );
    }

    /**
     * ➕ Subir documento a una carpeta
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|max:20480', // 20MB
            'folder_uid' => 'required|exists:folders,uid',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $folder = Folder::where('uid', $data['folder_uid'])->firstOrFail();

        DB::beginTransaction();

        try {
            $disk = config('filesystems.default'); // local / s3 / r2
            $file = $request->file('file');

            // 🔤 Nombre seguro
            $originalName = pathinfo(
                $file->getClientOriginalName(),
                PATHINFO_FILENAME
            );

            $extension = $file->getClientOriginalExtension();

            $safeName = Str::of($originalName)
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/i', '_')
                ->trim('_');

            $prefix = Str::random(8);
            $filename = "{$prefix}_{$safeName}.{$extension}";

            // 📁 Path basado en carpeta
            $path = "documents/{$folder->uid}/{$filename}";

            Storage::disk($disk)->put(
                $path,
                file_get_contents($file)
            );

            $document = Document::create([
                'title' => $data['title'] ?? $file->getClientOriginalName(),
                'description' => $data['description'] ?? null,
                'file_name' => $file->getClientOriginalName(),
                'file_url' => $path,
                'storage_service' => $disk,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'folder_id' => $folder->id,
            ]);

            DB::commit();

            return response()->json($document, 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error al subir documento', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al subir el documento',
            ], 500);
        }
    }

    /**
     * 👁️ Ver documento (inline)
     */
    public function view(string $uid)
    {
        $document = Document::where('uid', $uid)->firstOrFail();

        $disk = $document->storage_service;
        $path = $document->file_url;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'Archivo no encontrado');
        }

        return response()->file(
            Storage::disk($disk)->path($path),
            [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'inline; filename="'.$document->file_name.'"',
            ]
        );
    }

    /**
     * 👁️ Ver detalle de documento
     */
    public function show(string $uid)
    {
        $document = Document::where('uid', $uid)
            ->with(['folder', 'uploader.profile'])
            ->firstOrFail();

        return response()->json($document);
    }

    /**
     * ⬇️ Descargar documento (público)
     */
    public function download(string $uid)
    {
        $document = Document::where('uid', $uid)->firstOrFail();

        $disk = $document->storage_service;
        $path = $document->file_url;

        if (! Storage::disk($disk)->exists($path)) {
            return response()->json([
                'message' => 'Archivo no encontrado',
            ], 404);
        }

        return Storage::disk($disk)->download(
            $path,
            $document->file_name
        );
    }

    /**
     * 🗑️ Eliminar documento
     */
    public function destroy(string $uid)
    {
        try {
            $document = Document::where('uid', $uid)->firstOrFail();

            $disk = $document->storage_service;
            $path = $document->file_url;

            if ($disk && $path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }

            $document->delete();

            return response()->json([
                'message' => 'Documento eliminado correctamente',
            ]);

        } catch (\Throwable $e) {
            Log::error('Error al eliminar documento', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo eliminar el documento',
            ], 500);
        }
    }
}
