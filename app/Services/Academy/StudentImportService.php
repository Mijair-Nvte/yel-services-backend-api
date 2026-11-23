<?php

namespace App\Services\Academy;

use App\Models\OrgCompany;
use App\Models\OrgCourse;
use App\Models\OrgCourseEnrollment;
use App\Models\OrgCustomer;
use App\Models\User;
use App\Traits\HandlesCustomers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentImportService
{
    use HandlesCustomers;

    public const IMPORTED = 'imported';
    public const ALREADY_ENROLLED = 'already_enrolled';
    public const INVALID_EMAIL = 'invalid_email';

    /**
     * Importa un contacto: identidad (users) + CRM (org_customers) + acceso (enrollment).
     *
     * @param  array{name?: string|null, email?: string|null, phone?: string|null, source?: string|null}  $contact
     * @return self::IMPORTED|self::ALREADY_ENROLLED|self::INVALID_EMAIL
     */
    public function importContact(OrgCompany $company, OrgCourse $course, array $contact): string
    {
        $email = Str::lower(trim((string) ($contact['email'] ?? '')));

        // Kajabi siempre trae email, pero protegemos contra filas mal capturadas
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::INVALID_EMAIL;
        }

        $fullName = trim((string) ($contact['name'] ?? '')) ?: Str::before($email, '@');
        $phone = trim((string) ($contact['phone'] ?? '')) ?: null;
        $source = trim((string) ($contact['source'] ?? '')) ?: 'kajabi_import';

        [$firstName, $lastName] = $this->splitName($fullName);

        return DB::transaction(function () use ($company, $course, $email, $fullName, $firstName, $lastName, $phone, $source) {
            // 1. Identidad global (users)
            $user = $this->findOrCreateUser($email, $fullName, $firstName, $lastName, $phone);

            // 2. Ficha en el CRM de esta empresa (org_customers), vinculada al user
            $this->linkCustomer($company, $user, $email, $fullName, $phone);

            // 3. Acceso al curso (org_course_enrollments)
            $enrollment = OrgCourseEnrollment::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'org_course_id' => $course->id,
                ],
                [
                    'org_company_id' => $company->id,
                    'source' => $source,
                    'status' => 'active',
                    'enrolled_at' => now(),
                ]
            );

            // Si existía pero estaba cancelada o expirada, la reactivamos
            if (! $enrollment->wasRecentlyCreated && $enrollment->status !== 'active') {
                $enrollment->update(['status' => 'active', 'expires_at' => null]);

                return self::IMPORTED;
            }

            return $enrollment->wasRecentlyCreated ? self::IMPORTED : self::ALREADY_ENROLLED;
        });
    }

    private function findOrCreateUser(
        string $email,
        string $fullName,
        string $firstName,
        ?string $lastName,
        ?string $phone
    ): User {
        $user = User::withTrashed()->where('email', $email)->first();

        // Ya existe: no tocamos su contraseña ni su perfil
        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }

            if (is_null($user->email_verified_at)) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user;
        }

        // Nuevo: cuenta creada, pendiente de contraseña
        $user = new User();
        $user->forceFill([
            'name' => $fullName,
            'email' => $email,
            'password' => Str::random(40),   // Nadie la conoce: debe crear la suya
            'email_verified_at' => now(),    // Kajabi ya verificó el correo
            'password_set_at' => null,
            'imported_from' => 'kajabi',
        ])->save();

        // El hook "created" del modelo ya creó el perfil vacío: lo llenamos
        $user->profile()->update([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
        ]);

        return $user;
    }

    /**
     * Reutiliza el trait (misma lógica que los webhooks de GHL),
     * pero busca SOLO por email para no vincular el customer de otra persona por teléfono.
     */
    private function linkCustomer(OrgCompany $company, User $user, string $email, string $fullName, ?string $phone): void
    {
        $customerId = $this->findOrCreateCustomer($company->id, $fullName, $email, null);

        $customer = OrgCustomer::find($customerId);

        if (! $customer) {
            return;
        }

        $updates = [];

        // Vincular solo si el customer es de este correo y no tiene otro dueño
        if (is_null($customer->user_id) && Str::lower((string) $customer->email) === $email) {
            $updates['user_id'] = $user->id;
        }

        // Completar el teléfono sin pisar uno existente
        if ($phone && empty($customer->phone)) {
            $updates['phone'] = $phone;
        }

        if ($updates) {
            $customer->update($updates);
        }
    }

    /** @return array{0: string, 1: string|null} */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [$fullName];

        return [$parts[0], $parts[1] ?? null];
    }
}