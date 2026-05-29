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
     * Inscribe a un usuario en el programa de Partners de una empresa.
     */
    public function joinProgram(User $user, OrgCompany $company): OrgCompanyPartner
    {
        // 1. Validar que no sea partner previamente
        $exists = OrgCompanyPartner::where('org_company_id', $company->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            throw new Exception('El usuario ya está inscrito como partner en esta compañía.');
        }

        // 2. Ejecutar todo en una transacción de base de datos
        return DB::transaction(function () use ($user, $company) {

            // A. Crear el registro con el código autogenerado
            $partner = OrgCompanyPartner::create([
                'org_company_id' => $company->id,
                'user_id' => $user->id,
                'referral_code' => $this->generateUniqueReferralCode($user),
                'is_active' => true,
            ]);

            // B. Asignar el rol usando Spatie Teams (multi-tenant)
            setPermissionsTeamId($company->id);
            $user->assignRole('partner');

            return $partner;
        });
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
