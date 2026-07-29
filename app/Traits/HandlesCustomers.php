<?php

namespace App\Traits;

use App\Models\OrgCustomer;

trait HandlesCustomers
{
    protected function findOrCreateCustomer(int $companyId, string $fullName, ?string $email, ?string $phone): int
    {
        $customer = null;

        if (! empty($email)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)->where('email', $email)->first();
        }

        // 2. Si el cliente existe por correo, lo usamos.
        if ($customer) {
            // Opcional: Actualizamos el teléfono en la base de datos si el cliente introdujo uno nuevo o diferente
            if (! empty($phone) && $customer->phone !== $phone) {
                $customer->update(['phone' => $phone]);
            }

            return $customer->id;
        }

        // 3. Si NO existe el correo, creamos un nuevo usuario sin importar si el teléfono se repite
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? null;

        $newCustomer = OrgCustomer::create([
            'org_company_id' => $companyId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
        ]);

        return $newCustomer->id;
    }
}