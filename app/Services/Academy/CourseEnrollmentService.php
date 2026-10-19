<?php

namespace App\Services\Academy;

use App\Models\OrgCourseEnrollment;
use App\Models\OrgSale;
use App\Models\OrgCourse;
use App\Models\User;
use App\Traits\HandlesCustomers; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseEnrollmentService
{
    use HandlesCustomers;

    /**
     * Procesa la compra de un curso proveniente de Stripe
     */
    public function processCourseCheckout(array $session): void
    {
        $metadata = $session['metadata'] ?? [];
        $userId = $metadata['user_id'] ?? null;
        $courseId = $metadata['course_id'] ?? null;
        $companyId = $metadata['company_id'] ?? null;
        $referralCode = $metadata['referral_code'] ?? null;
        $totalPaid = $session['amount_total'] / 100;
        $stripeSessionId = $session['id'];

        if (!$userId || !$courseId || !$companyId) {
            Log::error('❌ Error en CourseEnrollmentService: Metadata incompleta.', ['session' => $stripeSessionId]);
            return;
        }

        $user = User::find($userId);
        $course = OrgCourse::find($courseId);
        $stripePaymentStatus = $session['payment_status'] === 'paid' ? 'paid' : 'pending';

        try {
            DB::transaction(function () use (
                $user, $course, $companyId, $totalPaid, $stripeSessionId, $stripePaymentStatus, $referralCode
            ) {
                // 1. Asegurar que el usuario exista como Customer en el CRM de la empresa (usando tu Trait)
                $customerId = $this->findOrCreateCustomer(
                    $companyId, 
                    $user->name, 
                    $user->email, 
                    $user->phone ?? null
                );

                // 2. Registrar la venta en org_sales (Dejamos org_service_id en NULL)
                OrgSale::create([
                    'org_company_id' => $companyId,
                    'org_customer_id' => $customerId,
                    'org_service_id' => null, 
                    'source_type' => 'stripe_course_checkout',
                    'source_id' => $stripeSessionId,
                    'customer_origin' => 'YEL CURSOS',
                    'product_name' => 'Curso: ' . $course->title,
                    'total_amount' => $totalPaid,
                    'payment_status' => $stripePaymentStatus,
                    'referral_code' => $referralCode,
                    // Si deseas calcular comisiones para cursos después, puedes aplicar la misma lógica aquí
                    'commission_amount' => 0, 
                    'commission_status' => 'not_applicable',
                ]);

                // 3. Inscribir al usuario en el curso
                if ($stripePaymentStatus === 'paid') {
                    $this->enrollUser($user->id, $course->id, $companyId, 'stripe_purchase');
                }
            });

        } catch (\Exception $e) {
            Log::critical('🚨 ERROR AL PROCESAR COMPRA DE CURSO.', [
                'error' => $e->getMessage(),
                'session_id' => $stripeSessionId,
            ]);
        }
    }

    /**
     * Inscribe a un usuario en un curso o reactiva su suscripción. (El que ya teníamos)
     */
    public function enrollUser(int $userId, int $courseId, int $companyId, string $source = 'free'): OrgCourseEnrollment
    {
        $enrollment = OrgCourseEnrollment::firstOrCreate(
            ['user_id' => $userId, 'org_course_id' => $courseId],
            [
                'org_company_id' => $companyId,
                'source' => $source,
                'status' => 'active',
                'enrolled_at' => now(),
            ]
        );

        if ($enrollment->status !== 'active') {
            $enrollment->update([
                'status' => 'active',
                'source' => $source
            ]);
        }

        return $enrollment;
    }
}