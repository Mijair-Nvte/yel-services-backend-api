<?php

namespace App\Http\Controllers\Api\Academy;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCourse;
use App\Models\OrgCourseEnrollment;
use Illuminate\Support\Facades\Auth;

class CourseCatalogController extends Controller
{
    /**
     * Lista todos los cursos publicados de la compañía.
     */
    public function index($companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $user = Auth::user();

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
                    'cover_url' => $course->cover_url, // 👈 CAMBIO AQUÍ: Usamos el Accessor
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
    public function show($companyUid, $courseUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $user = Auth::user();

        $course = OrgCourse::where('org_company_id', $company->id)
            ->where('uid', $courseUid)
            ->where('status', 'published')
            ->with(['modules' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order', 'asc');
            }, 'modules.lessons' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order', 'asc');
            }])
            ->firstOrFail();

        $isEnrolled = $user ? OrgCourseEnrollment::where('user_id', $user->id)
            ->where('org_course_id', $course->id)
            ->where('status', 'active')
            ->exists() : false;

        $courseData = [
            'uid' => $course->uid,
            'title' => $course->title,
            'description' => $course->description,
            'cover_url' => $course->cover_url,     // 👈 CAMBIO AQUÍ
            'preview_url' => $course->preview_url, // 👈 CAMBIO AQUÍ
            'is_free' => (bool) $course->is_free,
            'price' => $course->price,             // 👈 Aprovechamos y agregamos el precio que faltaba aquí
            'is_enrolled' => $isEnrolled,
            'modules' => $course->modules->map(function ($module) use ($isEnrolled) {
                return [
                    'uid' => $module->uid,
                    'title' => $module->title,
                    'description' => $module->description,
                    'lessons' => $module->lessons->map(function ($lesson) use ($isEnrolled) {
                        $canWatch = $isEnrolled || $lesson->is_free_preview;

                        return [
                            'uid' => $lesson->uid,
                            'title' => $lesson->title,
                            'duration_seconds' => $lesson->duration_seconds,
                            'is_free_preview' => (bool) $lesson->is_free_preview,
                            'video_url' => $canWatch ? $lesson->video_url : null, // 👈 CAMBIO AQUÍ: Usamos video_url del modelo
                            'can_watch' => $canWatch,
                        ];
                    }),
                ];
            }),
        ];

        return response()->json(['data' => $courseData]);
    }

    /**
     * Inscribe al usuario actual en un curso gratuito.
     */
    public function enroll($companyUid, $courseUid)
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

        $enrollment = OrgCourseEnrollment::firstOrCreate(
            ['user_id' => $user->id, 'org_course_id' => $course->id],
            [
                'org_company_id' => $company->id,
                'source' => 'free',
                'status' => 'active',
            ]
        );

        // Si ya estaba cancelado/expirado, lo reactivamos
        if ($enrollment->status !== 'active') {
            $enrollment->update(['status' => 'active']);
        }

        return response()->json([
            'message' => 'Inscripción exitosa',
            'data' => ['is_enrolled' => true],
        ]);
    }
}
