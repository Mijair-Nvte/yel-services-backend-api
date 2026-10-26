<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCourse;
use App\Models\OrgCourseEnrollment;
use App\Models\OrgCustomer;
use App\Models\User;
use App\Traits\HandlesCustomers;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrgCourseController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace, HandlesCustomers;

    /**
     * 📋 Listar todos los cursos de la compañía con paginación y filtros
     */
    public function index(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('view_courses');

            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            $query = OrgCourse::where('org_company_id', $company->id)
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $courses = $query->paginate($perPage);

            // KPIs rápidos para la vista principal
            $totalPublished = OrgCourse::where('org_company_id', $company->id)->where('status', 'published')->count();
            $totalDrafts = OrgCourse::where('org_company_id', $company->id)->where('status', 'draft')->count();

            return response()->json([
                'data' => $courses->items(),
                'meta' => [
                    'current_page' => $courses->currentPage(),
                    'per_page' => $courses->perPage(),
                    'total' => $courses->total(),
                    'last_page' => $courses->lastPage(),
                    'kpis' => [
                        'total_published' => $totalPublished,
                        'total_drafts' => $totalDrafts,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar los cursos.'], 500);
        }
    }

    /**
     * 👁️ Ver detalle de un curso específico
     */
    public function show(string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('view_courses');

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json(['data' => $course], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Curso no encontrado.'], 404);
        }
    }

    /**
     * ☁️ Generar URL Pre-firmada para subidas directas a R2 (Estructura Limpia por Curso)
     */
    public function presign(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'file_name' => 'required|string',
                'mime_type' => 'required|string',
                'type' => 'required|in:cover,preview',
                'course_uid' => 'required|string', // AHORA ES OBLIGATORIO
            ]);

            $fileName = $request->file_name;
            $mimeType = $request->mime_type;
            $type = $request->type;
            $courseUid = $request->course_uid;

            // Al ser obligatorio, forzamos la estructura estricta:
            // workspace_uid/courses/course_uid/covers/ O /previews/
            $folderName = $type === 'cover' ? 'covers' : 'previews';
            $path = "{$company->uid}/courses/{$courseUid}/{$folderName}/".time().'_'.Str::slug(pathinfo($fileName, PATHINFO_FILENAME)).'.'.pathinfo($fileName, PATHINFO_EXTENSION);

            $disk = Storage::disk('r2_public');
            $client = $disk->getClient();
            $bucket = $disk->getConfig()['bucket'];

            $command = $client->getCommand('PutObject', [
                'Bucket' => $bucket,
                'Key' => $path,
                'ContentType' => $mimeType,
            ]);

            $signedRequest = $client->createPresignedRequest($command, '+15 minutes');
            $uploadUrl = (string) $signedRequest->getUri();
            $publicUrl = $disk->url($path);

            return response()->json([
                'upload_url' => $uploadUrl,
                'path' => $path,
                'public_url' => $publicUrl,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar la pre-firma: '.$e->getMessage()], 500);
        }
    }

    /**
     * ✅ Confirmar subida (Opcional)
     */
    public function confirm(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'path' => 'required|string',
            ]);

            $path = $request->path;
            $disk = Storage::disk('r2_public');

            if (! $disk->exists($path)) {
                return response()->json(['message' => 'El archivo no se encuentra en el almacenamiento.'], 404);
            }

            return response()->json([
                'message' => 'Archivo confirmado correctamente.',
                'public_url' => $disk->url($path),
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al confirmar el archivo.'], 500);
        }
    }

    /**
     * ➕ Crear un nuevo curso
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'title' => 'required|string|max:255',
                'slug' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|in:draft,published,archived',
                'is_active' => 'boolean',
                'is_free' => 'boolean',
                'price' => 'nullable|numeric|min:0',
                'revenuecat_entitlement_id' => 'nullable|string|max:255',
                'cover_image_url' => 'nullable|string',
                'preview_video_url' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            $exists = OrgCourse::where('org_company_id', $company->id)
                ->where('slug', $request->slug)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'El slug ya está en uso por otro curso en esta empresa.'], 422);
            }

            $course = OrgCourse::create([
                'uid' => 'crs_'.Str::random(16),
                'org_company_id' => $company->id,
                'created_by' => auth()->id(),
                'title' => $request->title,
                'slug' => $request->slug,
                'description' => $request->description,
                'status' => $request->status,
                'is_active' => $request->is_active ?? true,
                'is_free' => $request->is_free ?? true,
                'price' => $request->is_free ? null : $request->price,
                'revenuecat_entitlement_id' => $request->is_free ? null : $request->revenuecat_entitlement_id,
                'cover_image_url' => $request->cover_image_url,
                'preview_video_url' => $request->preview_video_url,
                'metadata' => $request->metadata,
            ]);

            return response()->json([
                'message' => 'Curso creado correctamente.',
                'data' => $course,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el curso: '.$e->getMessage()], 500);
        }
    }

    /**
     * ✏️ Actualizar un curso existente
     */
    public function update(Request $request, string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'slug' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|required|in:draft,published,archived',
                'is_active' => 'boolean',
                'is_free' => 'boolean',
                'price' => 'nullable|numeric|min:0',
                'revenuecat_entitlement_id' => 'nullable|string|max:255',
                'cover_image_url' => 'nullable|string',
                'preview_video_url' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            if ($request->has('slug') && $request->slug !== $course->slug) {
                $exists = OrgCourse::where('org_company_id', $company->id)
                    ->where('slug', $request->slug)
                    ->exists();

                if ($exists) {
                    return response()->json(['message' => 'El slug ya está en uso por otro curso.'], 422);
                }
            }

            $course->update($request->all());

            return response()->json([
                'message' => 'Curso actualizado correctamente.',
                'data' => $course,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el curso.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar un Curso (portada, preview, recursos del curso, módulos, lecciones y sus archivos en R2)
     */
    public function destroy(string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->with(['resources', 'modules.resources', 'modules.lessons.resources'])
                ->firstOrFail();

            // 1. Borrar portada y video promocional del curso
            if (! empty($course->cover_image_url)) {
                Storage::disk('r2_public')->delete($course->cover_image_url);
            }
            if (! empty($course->preview_video_url)) {
                Storage::disk('r2_public')->delete($course->preview_video_url);
            }

            // 2. Borrar recursos directos del curso
            foreach ($course->resources as $resource) {
                if (! empty($resource->file_url)) {
                    Storage::disk('r2_public')->delete($resource->file_url);
                }
                $resource->delete();
            }

            // 3. Limpiar módulos, lecciones y sus recursos
            foreach ($course->modules as $module) {
                foreach ($module->resources as $resource) {
                    if (! empty($resource->file_url)) {
                        Storage::disk('r2_public')->delete($resource->file_url);
                    }
                    $resource->delete();
                }

                foreach ($module->lessons as $lesson) {
                    if (! empty($lesson->video_path)) {
                        Storage::disk('r2_public')->delete($lesson->video_path);
                    }
                    foreach ($lesson->resources as $resource) {
                        if (! empty($resource->file_url)) {
                            Storage::disk('r2_public')->delete($resource->file_url);
                        }
                        $resource->delete();
                    }
                    $lesson->delete();
                }

                $module->delete();
            }

            $course->delete();

            return response()->json(['message' => 'Curso y todo su contenido eliminados correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el curso: '.$e->getMessage()], 500);
        }
    }

    /**
     * 📥 Importar suscriptores masivamente a un curso
     */
    public function importSubscribers(Request $request, string $uid, string $courseUid)
    {
        try {
            // 1. Validar compañía y permisos
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            // 2. Validar el curso
            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $contacts = $request->input('contacts', []);

            if (empty($contacts) || ! is_array($contacts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionaron contactos válidos para importar.',
                ], 400);
            }

            $importedCount = 0;
            $skippedCount = 0;

            // 3. Iterar y procesar cada contacto
            foreach ($contacts as $contact) {
                $fullName = trim($contact['name'] ?? 'Alumno Desconocido');
                $email = trim($contact['email'] ?? null);
                $phone = trim($contact['phone'] ?? null);
                $source = trim($contact['source'] ?? 'Importación Masiva Kajabi');

                // Si no hay correo, no podemos crearle cuenta en el LMS
                if (empty($email)) {
                    $skippedCount++;

                    continue;
                }

                // 🎯 TRAIT: Encuentra o crea el cliente en el CRM
                $customerId = $this->findOrCreateCustomer(
                    $company->id,
                    $fullName,
                    $email,
                    $phone
                );

                $customer = OrgCustomer::find($customerId);

                // 🚦 CREAR Y AUTO-VERIFICAR AL USUARIO PARA EL LMS
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $fullName,
                        'password' => Hash::make(Str::random(12)),
                        'email_verified_at' => now(),
                    ]
                );

                // Si el usuario ya existía en BD pero no estaba verificado, lo verificamos ahora
                if (empty($user->email_verified_at)) {
                    $user->email_verified_at = now();
                    $user->save();
                }

                // Vincular el Customer del CRM con el User del LMS
                if (empty($customer->user_id)) {
                    $customer->update(['user_id' => $user->id]);
                }

                // =========================================================
                // 🚀 NUEVA LÓGICA: VINCULAR A LA COMPAÑÍA Y ASIGNAR ROL
                // =========================================================

                // 1. Vincular al usuario con el Workspace en la tabla pivot org_company_users
                \Illuminate\Support\Facades\DB::table('org_company_users')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'org_company_id' => $company->id,
                    ],
                    [
                        'is_active' => 1,
                        'created_at' => now(), // En updateOrInsert se ignorará el update de created_at si ya existe, pero asegura que lo tenga al crear
                        'updated_at' => now(),
                    ]
                );

                // 2. Asignar el rol de Spatie 'partner'
                try {
                    // Establecemos el ID del team/company para Spatie
                    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($company->id);

                    if (! $user->hasRole('partner')) {
                        $user->assignRole('partner');
                    }
                } catch (\Exception $e) {
                    $user->assignRole('partner');
                }
                // =========================================================

                // 🚦 VALIDAR DUPLICIDAD DE INSCRIPCIÓN EN EL CURSO
                $alreadyEnrolled = OrgCourseEnrollment::where('org_course_id', $course->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if ($alreadyEnrolled) {
                    $skippedCount++;

                    continue;
                }

                // ✅ INSCRIBIR AL USUARIO
                OrgCourseEnrollment::create([
                    'org_company_id' => $company->id,
                    'user_id' => $user->id,
                    'org_course_id' => $course->id,
                    'source' => $source,
                    'status' => 'active',
                    'enrolled_at' => now(),
                ]);

                $importedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Importación completada. $importedCount alumnos inscritos, $skippedCount omitidos.",
                'data' => [
                    'imported' => $importedCount,
                    'skipped' => $skippedCount,
                    'total' => count($contacts),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('🔥 ERROR AL IMPORTAR ALUMNOS: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la importación. Revisa los logs para más detalles.',
            ], 500);
        }
    }

    /**
     * 🎓 Obtener los estudiantes inscritos en un curso específico
     */
    public function getEnrolledStudents(Request $request, string $uid, string $courseUid)
    {
        try {
            // 1. Validar compañía y permisos
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_courses'); // o 'manage_courses' dependiendo de tus roles

            // 2. Validar el curso
            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            // 3. Consultar inscripciones con su usuario relacionado
            $query = OrgCourseEnrollment::with(['user' => function ($q) {
                $q->select('id', 'name', 'email'); // Traemos los datos clave del usuario
            }])
                ->where('org_company_id', $company->id)
                ->where('org_course_id', $course->id)
                ->orderBy('created_at', 'desc');

            // 4. Búsqueda por nombre o email del usuario
            if ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $enrollments = $query->paginate($perPage);

            // 5. KPIs rápidos de estudiantes (opcional, pero útil para el frontend)
            $totalActive = OrgCourseEnrollment::where('org_course_id', $course->id)->where('status', 'active')->count();

            return response()->json([
                'data' => $enrollments->items(),
                'meta' => [
                    'current_page' => $enrollments->currentPage(),
                    'per_page' => $enrollments->perPage(),
                    'total' => $enrollments->total(),
                    'last_page' => $enrollments->lastPage(),
                    'kpis' => [
                        'total_active' => $totalActive,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar los estudiantes inscritos.'], 500);
        }
    }
}
