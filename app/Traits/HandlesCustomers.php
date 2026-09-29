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

        // Limpieza de datos de entrada
        $email = !empty($email) ? strtolower(trim($email)) : null;
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = null; // Ignoramos emails mal formados
        }
        
        $phone = !empty($phone) ? trim($phone) : null;
        $ghlContactId = !empty($ghlContactId) ? trim($ghlContactId) : null;

        // 1. BÚSQUEDA JERÁRQUICA INTELIGENTE (Para evitar duplicados)
        // Prioridad 1: Por el GHL Contact ID (La llave más certera si ya existe en GHL)
        if (!empty($ghlContactId)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)
                                   ->where('contact_id', $ghlContactId)
                                   ->first();
        }

        // Prioridad 2: Si no se encontró por ID y tenemos correo, buscamos por correo
        if (!$customer && !empty($email)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)
                                   ->where('email', $email)
                                   ->first();
        }

        // Prioridad 3: Si aún no se encuentra y tenemos teléfono, buscamos por teléfono
        if (!$customer && !empty($phone)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)
                                   ->where('phone', $phone)
                                   ->first();
        }

        // 2. SI EL CLIENTE YA EXISTE, ACTUALIZAMOS DATOS FALTANTES
        if ($customer) {
            $updateData = [];

            if (!empty($email) && empty($customer->email)) {
                $updateData['email'] = $email;
            }
            if (!empty($phone) && empty($customer->phone)) {
                $updateData['phone'] = $phone;
            }
            if (!empty($ghlContactId) && empty($customer->contact_id)) {
                $updateData['contact_id'] = $ghlContactId;
            }

            // Opcional: Actualizar nombre si el anterior era genérico
            if (!empty($fullName) && ($customer->first_name === 'Cliente' || empty($customer->first_name))) {
                $nameParts = explode(' ', trim($fullName), 2);
                $updateData['first_name'] = $nameParts[0] ?? $customer->first_name;
                $updateData['last_name'] = $nameParts[1] ?? $customer->last_name;
            }

            if (!empty($updateData)) {
                $customer->update($updateData);
            }

            return $customer->id;
        }

        // 3. SI NO EXISTE NINGUNO, CREAMOS EL NUEVO CLIENTE (Incluso si viene sin correo ni teléfono)
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? null;

        $newCustomer = OrgCustomer::create([
            'org_company_id' => $companyId,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'email'          => $email,
            'phone'          => $phone,
            'contact_id'     => $ghlContactId,
        ]);

        return $newCustomer->id;
    }
}