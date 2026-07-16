<?php

namespace App\Traits;

use App\Models\OrgCustomer;

trait HandlesCustomers
{
    protected function findOrCreateCustomer(int $companyId, string $fullName, ?string $email, ?string $phone): int
    {
        $customer = null;

        if (!empty($email)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)->where('email', $email)->first();
        }

        if (!$customer && !empty($phone)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)->where('phone', $phone)->first();
        }

        if ($customer) {
            return $customer->id;
        }

        $nameParts = explode(' ', trim($fullName), 2);
        
        $newCustomer = OrgCustomer::create([
            'org_company_id' => $companyId,
            'first_name'     => $nameParts[0] ?? 'Cliente',
            'last_name'      => $nameParts[1] ?? null,
            'email'          => $email,
            'phone'          => $phone,
        ]);

        return $newCustomer->id;
    }
}