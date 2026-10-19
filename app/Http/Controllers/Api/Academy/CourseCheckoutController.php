<?php

namespace App\Http\Controllers\Api\Academy;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCourse;
use Illuminate\Http\Request;

class CourseCheckoutController extends Controller
{
    public function createSession(Request $request, string $companyUid, string $courseUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $course = OrgCourse::where('uid', $courseUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        if ($course->is_free || $course->price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Este curso es gratuito, usa el método de inscripción directa.'
            ], 400);
        }

        $user = $request->user();

        $metadata = [
            'type' => 'course_purchase',
            'course_uid' => $course->uid,
            'course_id' => $course->id,
            'company_uid' => $company->uid,
            'company_id' => $company->id,
            'user_id' => $user->id,
            'referral_code' => $request->referral_code ?? null,
        ];

       
        $coursesUrl = config('app.courses_url');

        try {
            $checkout = $user->checkoutCharge(
                $course->price * 100, 
                $course->title, 
                1, 
                [
                    // Redirigimos al usuario a su dashboard si paga exitosamente
                    'success_url' => $coursesUrl . "/dashboard/{$company->uid}/courses?payment=success&course={$course->uid}",
                    // Redirigimos al catálogo público si cancela
                    'cancel_url' => $coursesUrl . "/cursos/{$course->slug}?payment=cancelled",
                    'metadata' => $metadata,
                    'payment_intent_data' => ['metadata' => $metadata],
                ]
            );

            return response()->json([
                'success' => true,
                'url' => $checkout->url,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }
}