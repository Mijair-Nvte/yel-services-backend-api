<?php

namespace App\Services\Partner;

use App\Models\OrgCompany;
use App\Models\OrgCompanyPartner;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerService
{
    /**
     * Inscribe a un usuario en el programa de Partners de una empresa (Estado Pendiente).
     */
    public function joinProgram(User $user, OrgCompany $company, array $taxFormData): OrgCompanyPartner
    {
        // 1. Validar que no sea partner previamente o tenga solicitud en curso
        $exists = OrgCompanyPartner::where('org_company_id', $company->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            throw new Exception('Ya tienes una solicitud en proceso o estás inscrito como partner en esta compañía.');
        }

        // 2. Ejecutar todo en una transacción de base de datos
        return DB::transaction(function () use ($user, $company, $taxFormData) {

            // A. Crear el registro en estatus pendiente, sin código de referido, e inyectando el JSON
            $partner = OrgCompanyPartner::create([
                'org_company_id' => $company->id,
                'user_id'        => $user->id,
                'referral_code'  => null,
                'status'         => 'pending',
                'tax_form_type'  => $taxFormData['tax_form_type'],
                'tax_form_data'  => $taxFormData, // Eloquent lo convierte a JSON por el $casts en el modelo
            ]);

            // B. Asignar el rol usando Spatie Teams (multi-tenant)
            // Se le asigna el rol para que pueda entrar a la vista, pero el frontend leerá el estatus "pending"
            setPermissionsTeamId($company->id);
            $user->assignRole('partner');

            return $partner;
        });
    }

    /**
     * Método para uso del Administrador: Aprueba a un afiliado y le genera su código.
     */
    public function approvePartner(OrgCompanyPartner $partner): OrgCompanyPartner
    {
        if ($partner->status === 'approved') {
            throw new Exception('El partner ya se encuentra aprobado.');
        }

        $partner->update([
            'status'        => 'approved',
            'referral_code' => $this->generateUniqueReferralCode($partner->user),
        ]);

        return $partner;
    }

    /**
     * Método para uso del Administrador: Rechaza a un afiliado.
     */
    public function rejectPartner(OrgCompanyPartner $partner): OrgCompanyPartner
    {
        $partner->update([
            'status' => 'rejected',
        ]);

        return $partner;
    }

    /**
     * Genera un código de referido único (Ej: MIJ-8A2F)
     */
    private function generateUniqueReferralCode(User $user): string
    {
        // Limpiamos el nombre y tomamos las primeras 3 letras
        $cleanName = strtoupper(Str::slug($user->name, ''));
        $prefix = substr($cleanName, 0, 3);

        // Si el nombre es muy corto o tiene caracteres extraños, rellenamos
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }

        $isUnique = false;
        $code = '';

        // Aseguramos que sea único en la BD
        while (! $isUnique) {
            // Str::upper + Str::random para generar sufijo (ej: 8A2F)
            $randomSuffix = strtoupper(Str::random(4));
            $code = $prefix.'-'.$randomSuffix;

            if (! OrgCompanyPartner::where('referral_code', $code)->exists()) {
                $isUnique = true;
            }
        }

        return $code;
    }
}