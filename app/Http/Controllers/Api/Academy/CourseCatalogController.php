<?php

namespace App\Http\Controllers\Api\Academy;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCourse;
use App\Models\OrgCourseEnrollment;
use App\Services\Academy\CourseEnrollmentService;
use Illuminate\Support\Facades\Auth;

class CourseCatalogController extends Controller
{
    /**
     * Lista todos los cursos publicados de la compañía.
     */
    public function index($companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        // 👈 CAMBIO: Intentamos obtener al usuario por Sanctum sin requerir autenticación
        $user = auth('sanctum')->user();

        $courses = OrgCourse::where('org_company_id', $company->id)
            ->where('status', 'published')
            ->where('is_active', true)
            ->withCount('modules')
            ->get()
            ->map(function ($course) use ($user) {
                $isEnrolled = $user ? OrgCourseEnrollment::where('user_id', $user->id)
                    ->where('org_course_id', $course->id)
                    ->where('status', 'active')
                    ->exists() : false;

                return [
                    'uid' => $course->uid,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'description' => $course->description,
                    'cover_url' => $course->cover_url,
                    'is_free' => (bool) $course->is_free,
                    'price' => $course->price,
                    'modules_count' => $course->modules_count,
                    'is_enrolled' => $isEnrolled,
                ];
            });

        return response()->json(['data' => $courses]);
    }

    /**
     * Muestra el detalle del curso, sus módulos y lecciones.
     */
    public function show($companyUid, $slug)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $user = auth('sanctum')->user();

        $course = OrgCourse::where('org_company_id', $company->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'resources',
                'modules' => function ($query) {
                    $query->where('is_active', true)->orderBy('sort_order', 'asc')->with('resources');
                },
                'modules.lessons' => function ($query) {
                    $query->where('is_active', true)->orderBy('sort_order', 'asc')->with('resources');
                },
            ])
            ->firstOrFail();

        $isEnrolled = $user ? OrgCourseEnrollment::where('user_id', $user->id)
            ->where('org_course_id', $course->id)
            ->where('status', 'active')
            ->exists() : false;

        $formatResource = function ($resource, bool $hasAccess) {
            $data = [
                'uid' => $resource->uid,
                'title' => $resource->title,
                'file_name' => $resource->file_name,
                'mime_type' => $resource->mime_type,
            ];

            if ($hasAccess) {
                $data['public_url'] = $resource->public_url;
                $data['file_size'] = $resource->file_size;
            }

            return $data;
        };

        $courseData = [
            'uid' => $course->uid,
            'title' => $course->title,
            'description' => $course->description,
            'cover_url' => $course->cover_url,
            'preview_url' => $course->preview_url,
            'is_free' => (bool) $course->is_free,
            'price' => $course->price,
            'is_enrolled' => $isEnrolled,
            'resources' => $course->resources->map(fn ($r) => $formatResource($r, $isEnrolled)),

       
            'modules' => $course->modules->map(function ($module) use ($isEnrolled, $formatResource) {
                return [
                    'uid' => $module->uid,
                    'title' => $module->title,
                    'description' => $module->description,
                    'resources' => $module->resources->map(fn ($r) => $formatResource($r, $isEnrolled)),

                 
                    'lessons' => $module->lessons->map(function ($lesson) use ($isEnrolled, $formatResource) {
                        $canWatch = $isEnrolled || $lesson->is_free_preview;

                        $lessonData = [
                            'uid' => $lesson->uid,
                            'title' => $lesson->title,
                            'duration_seconds' => $lesson->duration_seconds,
                            'is_free_preview' => (bool) $lesson->is_free_preview,
                            'can_watch' => $canWatch,
                            'resources' => $lesson->resources->map(fn ($r) => $formatResource($r, $canWatch)),
                        ];

                        if ($canWatch) {
                            $lessonData['video_url'] = $lesson->video_url;
                        }

                        return $lessonData;
                    }),
                ];
            }),
        ];

        return response()->json(['data' => $courseData]);
    }

    /**
     * Inscribe al usuario actual en un curso gratuito.
     */
    public function enroll(string $companyUid, string $courseUid, CourseEnrollmentService $enrollmentService)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $user = Auth::user();

        $course = OrgCourse::where('org_company_id', $company->id)
            ->where('uid', $courseUid)
            ->where('status', 'published')
            ->firstOrFail();

        if (! $course->is_free) {
            return response()->json(['message' => 'Este curso es de pago.'], 403);
        }

        $enrollmentService->enrollUser($user->id, $course->id, $company->id, 'free');

        return response()->json([
            'message' => 'Inscripción exitosa',
            'data' => ['is_enrolled' => true],
        ]);
    }
}
