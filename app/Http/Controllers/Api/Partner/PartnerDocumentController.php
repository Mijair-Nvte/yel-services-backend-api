<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Folder;
use App\Models\OrgCompany;
use App\Models\User;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PartnerDocumentController extends Controller
{
    /**
     * 🔑 Generar URL prefirmada de subida (R2) inyectando el prefijo del usuario de forma segura
     */
    public function presign(Request $request, string $companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $user = Auth::user();

        $data = $request->validate([
            'file_name' => 'required|string',
            'mime_type' => 'required|string',
            'folder_uid' => 'nullable|string', // Nullable en el request, pero internamente lo resolveremos
            'module' => 'nullable|string', 
        ]);

        // 🌟 Lógica de "Mi Unidad": Resolvemos la carpeta destino
        if (! empty($data['folder_uid'])) {
            // Si el usuario especificó una carpeta, la buscamos
            $folder = Folder::where('uid', $data['folder_uid'])
                ->where('folderable_type', User::class)
                ->where('folderable_id', $user->id)
                ->firstOrFail();
        } else {
            // Si no se especificó carpeta (va a la "raíz"), buscamos o creamos "Mi Unidad"
            $folder = Folder::firstOrCreate([
                'org_company_id' => $company->id,
                'folderable_type' => User::class,
                'folderable_id' => $user->id,
                'parent_id' => null, // Es la raíz absoluta
                'name' => 'Mi Unidad',
            ], [
                'created_by' => $user->id,
            ]);
        }

        // Ahora SIEMPRE tenemos un ID y UID de carpeta válido
        $folderId = $folder->id;
        $modulePath = "folders/{$folder->uid}";

        $extension = pathinfo($data['file_name'], PATHINFO_EXTENSION);
        $safeName = Str::slug(pathinfo($data['file_name'], PATHINFO_FILENAME));
        $shortHash = substr(md5(uniqid()), 0, 8);

        // 🔒 Estructura de prefijos limpios calculados en Backend para R2
        $key = "vault/user_{$user->id}/{$modulePath}/{$safeName}_$shortHash.{$extension}";

        // Configuración de Cliente S3 nativo para R2
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
            'folder_id' => $folderId, // 🚀 Devolvemos SIEMPRE un ID válido de carpeta (Mi Unidad o subcarpeta)
        ]);
    }

    /**
     * ✍️ Confirmar subida exitosa y guardar registro en base de datos
     */
    public function confirm(Request $request, string $companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $user = Auth::user();

        $data = $request->validate([
            'original_name' => 'required|string',
            'mime_type' => 'required|string',
            'file_size' => 'required|integer',
            'key' => 'required|string',
            // 🚀 Cambiado a required, porque presign() siempre devolverá el ID de "Mi Unidad" como mínimo
            'folder_id' => 'required|integer|exists:folders,id', 
        ]);

        // 🔒 Seguridad extra
        if (! Str::startsWith($data['key'], "vault/user_{$user->id}/")) {
            abort(403, 'Intento de manipulación de almacenamiento no autorizado.');
        }

        if (! Storage::disk('r2')->exists($data['key'])) {
            return response()->json(['message' => 'El archivo no se encuentra en el storage cloud.'], 422);
        }

        DB::beginTransaction();
        try {
            $document = Document::create([
                'uid' => 'doc_'.substr(md5(uniqid()), 0, 16),
                'org_company_id' => $company->id,
                'title' => $data['original_name'],
                'file_name' => $data['original_name'],
                'file_url' => $data['key'],
                'storage_service' => 'r2',
                'mime_type' => $data['mime_type'],
                'file_size' => $data['file_size'],
                'uploaded_by' => $user->id,
                'folder_id' => $data['folder_id'], // 🚀 Nunca más será nulo
            ]);

            DB::commit();

            return response()->json($document, 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al registrar documento privado: '.$e->getMessage());

            // 🧹 ROLLBACK FÍSICO: Eliminamos el archivo de Cloudflare R2 para no dejar huérfanos
            try {
                if (Storage::disk('r2')->exists($data['key'])) {
                    Storage::disk('r2')->delete($data['key']);
                    Log::info("Archivo huérfano eliminado de R2 tras fallo en BD: {$data['key']}");
                }
            } catch (\Exception $cleanupError) {
                Log::error('No se pudo eliminar archivo huérfano de R2: '.$cleanupError->getMessage());
            }

            // Retornamos el error técnico para que sepas qué columna está fallando en MySQL
            return response()->json([
                'message' => 'No se pudo guardar en la base de datos. El archivo fue eliminado de la nube.',
                'error_detail' => $e->getMessage() 
            ], 500);
        }
    }

    /**
     * 👁️ Ver o descargar archivo generando URL firmada temporal de lectura
     */
    public function view(string $companyUid, string $documentUid)
    {
        $user = Auth::user();

        $document = Document::where('uid', $documentUid)
            ->where('uploaded_by', $user->id)
            ->firstOrFail();

        $disk = $document->storage_service;

        if (in_array($disk, ['s3', 'r2'])) {
            $url = Storage::disk($disk)->temporaryUrl(
                $document->file_url,
                now()->addMinutes(15)
            );

            return response()->json(['url' => $url]);
        }

        return response()->json(['message' => 'Servicio de almacenamiento no soportado.'], 400);
    }

    /**
     * ⬇️ Obtener URL firmada para FORZAR DESCARGA
     */
    public function download(string $companyUid, string $documentUid)
    {
        $user = Auth::user();

        $document = Document::where('uid', $documentUid)
            ->where('uploaded_by', $user->id)
            ->firstOrFail();

        $disk = $document->storage_service;

        if (in_array($disk, ['s3', 'r2'])) {
            $client = Storage::disk($disk)->getClient();

            $command = $client->getCommand('GetObject', [
                'Bucket' => config("filesystems.disks.{$disk}.bucket"),
                'Key' => $document->file_url,
                'ResponseContentDisposition' => 'attachment; filename="'.$document->file_name.'"',
            ]);

            $request = $client->createPresignedRequest($command, '+15 minutes');

            return response()->json(['url' => (string) $request->getUri()]);
        }

        return response()->json(['message' => 'Almacenamiento no soportado'], 400);
    }

    /**
     * 🗑️ Eliminar un documento del usuario
     */
    public function destroy(string $companyUid, string $documentUid)
    {
        $user = Auth::user();

        $document = Document::where('uid', $documentUid)
            ->where('uploaded_by', $user->id)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            if ($document->storage_service && $document->file_url) {
                Storage::disk($document->storage_service)->delete($document->file_url);
            }

            $document->delete();
            DB::commit();

            return response()->json(['message' => 'Documento eliminado correctamente']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al eliminar documento de cliente: '.$e->getMessage());

            return response()->json(['message' => 'No se pudo procesar la eliminación.'], 500);
        }
    }
}