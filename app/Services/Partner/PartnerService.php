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
     * Inscribe a un usuario en el programa de Partners (Auto-Aprobado).
     * Si ya existe, verifica que esté aprobado y tenga código generado (Read-Repair).
     */
    public function joinProgram(User $user, OrgCompany $company): OrgCompanyPartner
    {
        $partner = OrgCompanyPartner::where('org_company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        // 1. Si NO existe, lo creamos directamente aprobado y con código
        if (! $partner) {
            return DB::transaction(function () use ($user, $company) {
                $newPartner = OrgCompanyPartner::create([
                    'org_company_id' => $company->id,
                    'user_id' => $user->id,
                    'referral_code' => $this->generateUniqueReferralCode($user),
                    'status' => 'approved',
                    'tax_form_type' => null,
                    'tax_form_data' => [], // Delegado a GoHighLevel
                ]);

                // Asegurar que tenga el rol
                setPermissionsTeamId($company->id);
                $user->assignRole('partner');

                return $newPartner;
            });
        }

        // 2. Si YA EXISTE, nos aseguramos de que no esté atascado en "pending" o sin código
        $needsUpdate = false;

        if ($partner->status === 'pending') {
            $partner->status = 'approved';
            $needsUpdate = true;
        }

        if (empty($partner->referral_code)) {
            $partner->referral_code = $this->generateUniqueReferralCode($user);
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $partner->save();
        }

        return $partner;
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
            'status' => 'approved',
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
