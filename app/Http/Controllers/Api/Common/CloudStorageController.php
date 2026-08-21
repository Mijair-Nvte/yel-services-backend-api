<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Services\R2StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CloudStorageController extends Controller
{
    protected $storageService;

    public function __construct(R2StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Endpoint centralizado para pedir URLs pre-firmadas
     */
    public function presign(Request $request)
    {
        $request->validate([
            'file_name' => 'required|string',
            'mime_type' => 'required|string',
            'module' => 'required|string|in:properties,documents,avatars,general',
            'company_uid' => 'required|string', // Aseguramos que venga el ID de la compañía
            'is_public' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $module = $request->module;
        $companyUid = $request->company_uid; // Usamos el ID del tenant
        $isPublic = $request->boolean('is_public', false);

        $disk = $isPublic ? 'r2_public' : 'r2';

        // Nueva jerarquía estandarizada:
        // vault/{company_uid}/{module}/...
        $basePath = "{$companyUid}/user_{$user->id}/{$module}";

        $result = $this->storageService->generatePresignedUrl(
            $disk,
            $basePath,
            $request->file_name,
            $request->mime_type
        );

        return response()->json($result);
    }
}
