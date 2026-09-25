<?php

namespace App\Traits;

use App\Models\OrgCustomer;

trait HandlesCustomers
{
    protected function findOrCreateCustomer(
        int $companyId, 
        string $fullName, 
        ?string $email, 
        ?string $phone, 
        ?string $ghlContactId = null
    ): int {
        $customer = null;

        if (! empty($email)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)->where('email', $email)->first();
        }

        // 2. Si el cliente existe por correo, lo usamos y actualizamos datos si es necesario.
        if ($customer) {
            $updateData = [];

            if (! empty($phone) && $customer->phone !== $phone) {
                $updateData['phone'] = $phone;
            }

            // Si nos pasan un ghl_contact_id y el cliente no lo tenía (o cambió), lo actualizamos
            if (! empty($ghlContactId) && $customer->ghl_contact_id !== $ghlContactId) {
                $updateData['contact_id'] = $ghlContactId;
            }

            if (!empty($updateData)) {
                $customer->update($updateData);
            }

            return $customer->id;
        }

        // 3. Si NO existe el correo, creamos un nuevo cliente
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? null;

        $newCustomer = OrgCustomer::create([
            'org_company_id'   => $companyId,
            'first_name'       => $firstName,
            'last_name'        => $lastName,
            'email'            => $email,
            'phone'            => $phone,
            'contact_id'   => $ghlContactId,
        ]);

        return $newCustomer->id;
    }
}