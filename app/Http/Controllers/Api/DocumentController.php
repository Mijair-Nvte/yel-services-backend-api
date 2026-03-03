<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Folder;
use Aws\S3\S3Client;
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

        // Si es R2 o S3, generamos una URL temporal y redirigimos
        if (in_array($disk, ['s3', 'r2'])) {
            $url = Storage::disk($disk)->temporaryUrl(
                $path,
                now()->addHours(24) // El link caduca en 24 horas (puedes ajustarlo)
            );

            return redirect($url);
        }

        // Fallback por si alguna vez usas el disco 'local'
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

        if (in_array($disk, ['s3', 'r2'])) {
            // Para descargar, usamos el cliente de S3 para forzar el encabezado "attachment"
            $client = Storage::disk($disk)->getClient();

            $command = $client->getCommand('GetObject', [
                'Bucket' => config("filesystems.disks.{$disk}.bucket"),
                'Key' => $path,
                'ResponseContentDisposition' => 'attachment; filename="'.$document->file_name.'"',
                'ResponseContentType' => $document->mime_type,
            ]);

            $request = $client->createPresignedRequest($command, '+24 hours');

            return redirect((string) $request->getUri());
        }

        // Fallback local
        return Storage::disk($disk)->download($path, $document->file_name);
    }

    /**
     * 🗑️ Eliminar documento
     */
    public function destroy(string $uid)
    {
        $document = Document::where('uid', $uid)->firstOrFail();

        $disk = $document->storage_service;
        $path = $document->file_url;

        DB::beginTransaction();

        try {
            // 1. Intentar borrar físicamente el archivo del Storage (R2/S3/Local)
            if ($disk && $path) {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                } else {
                    // Opcional: Registrar que el archivo no estaba en el disco,
                    // pero continuamos con el borrado en BD.
                    Log::warning("El archivo {$path} no se encontró en el disco {$disk} al intentar eliminarlo.");
                }
            }

            // 2. Si se borró del disco (o no estaba), borrar el registro de la BD
            $document->delete();

            DB::commit();

            return response()->json([
                'message' => 'Documento eliminado correctamente',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error al eliminar documento', [
                'document_uid' => $uid,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo eliminar el documento de la nube o la base de datos.',
            ], 500);
        }
    }

    public function presign(Request $request)
    {
        $data = $request->validate([
            'folder_uid' => 'required|exists:folders,uid',
            'file_name' => 'required|string',
            'mime_type' => 'required|string',
            'file_size' => 'required|integer',
        ]);

        $folder = Folder::where('uid', $data['folder_uid'])->firstOrFail();

        // Limpiar el nombre del archivo y la extensión
        $extension = pathinfo($data['file_name'], PATHINFO_EXTENSION);
        $originalName = pathinfo($data['file_name'], PATHINFO_FILENAME);

        // Hacer el nombre "seguro" (sin espacios raros, tildes, etc)
        $safeName = Str::slug($originalName);

        // Generar un hash corto (ej. 8 caracteres) para evitar colisiones
        $shortHash = substr(md5(uniqid()), 0, 8);

        // Limpiar el nombre de la carpeta
        $safeFolderName = Str::slug($folder->name);

        // Estructura limpia: documents/nombre-carpeta/nombre-archivo_hash.ext
        $key = "documents/{$safeFolderName}/{$safeName}_{$shortHash}.{$extension}";

        $client = new S3Client([
            'version' => 'latest',
            'region' => config('filesystems.disks.r2.region'),
            'endpoint' => config('filesystems.disks.r2.endpoint'),
            'credentials' => [
                'key' => config('filesystems.disks.r2.key'),
                'secret' => config('filesystems.disks.r2.secret'),
            ],
            'use_path_style_endpoint' => true,
        ]);

        $cmd = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.r2.bucket'),
            'Key' => $key,
            'ContentType' => $data['mime_type'],
        ]);

        $requestPresigned = $client->createPresignedRequest($cmd, '+15 minutes');

        return response()->json([
            'upload_url' => (string) $requestPresigned->getUri(),
            'key' => $key,
        ]);
    }

    public function confirm(Request $request)
    {
        $data = $request->validate([
            'folder_uid' => 'required|exists:folders,uid',
            'original_name' => 'required|string',
            'mime_type' => 'required|string',
            'file_size' => 'required|integer',
            'key' => 'required|string',
        ]);

        $folder = Folder::where('uid', $data['folder_uid'])->firstOrFail();

        // 🔒 VERIFICAR QUE EL ARCHIVO EXISTE EN R2
        if (! Storage::disk('r2')->exists($data['key'])) {
            return response()->json([
                'message' => 'El archivo no existe en el storage',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $document = Document::create([
                // Nombre visible (original)
                'title' => $data['original_name'],
                'file_name' => $data['original_name'],

                // Path real (UUID)
                'file_url' => $data['key'],
                'storage_service' => 'r2',

                'mime_type' => $data['mime_type'],
                'file_size' => $data['file_size'],
                'uploaded_by' => Auth::id(),
                'folder_id' => $folder->id,
            ]);

            DB::commit();

            return response()->json($document, 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error confirmando documento', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo confirmar el documento',
            ], 500);
        }
    }
}
