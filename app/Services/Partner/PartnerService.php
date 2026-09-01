<?php

namespace App\Services\Partner;

use App\Models\OrgCompany;
use App\Models\OrgCompanyPartner;
use App\Models\OrgPartnerTier;
use App\Models\OrgSellerType;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerService
{
    /**
     * Inscribe a un usuario en el programa de Partners (Auto-Aprobado).
     * Si ya existe, verifica que esté aprobado, tenga código generado y un tipo de vendedor asignado.
     *
     * @param  string  $sellerTypeSlug  Por defecto 'external' para afiliados que se registran solos.
     */
    public function joinProgram(User $user, OrgCompany $company, $sellerTypeConfig = 'external'): OrgCompanyPartner
    {
        $partner = OrgCompanyPartner::where('org_company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        $sellerTypeId = null;
        $defaultTierId = null;

        // Soportar tanto llamada por string (afiliados) como por array (vendedores internos desde admin)
        if (is_array($sellerTypeConfig)) {
            $sellerTypeId = $sellerTypeConfig['seller_type_id'] ?? null;
            $defaultTierId = $sellerTypeConfig['partner_tier_id'] ?? null;
        } else {
            // 1. Buscar tipo de vendedor por slug asegurando el scope de la compañía
            $sellerType = OrgSellerType::where('org_company_id', $company->id)
                ->where('slug', $sellerTypeConfig)
                ->first();

            $sellerTypeId = $sellerType ? $sellerType->id : null;

            // 2. Buscar el nivel por defecto filtrado por compañía (y opcionalmente por tipo)
            $defaultTier = OrgPartnerTier::where('org_company_id', $company->id)
                ->when($sellerTypeId, function ($query, $sellerTypeId) {
                    return $query->where('org_seller_type_id', $sellerTypeId);
                })
                ->orderBy('id', 'asc')
                ->first();

            $defaultTierId = $defaultTier ? $defaultTier->id : null;
        }

        // 3. Si NO existe el partner, lo creamos
        if (! $partner) {
            return DB::transaction(function () use ($user, $company, $sellerTypeId, $defaultTierId) {
                $newPartner = OrgCompanyPartner::create([
                    'org_company_id' => $company->id,
                    'user_id' => $user->id,
                    'org_seller_type_id' => $sellerTypeId,
                    'org_partner_tier_id' => $defaultTierId,
                    'referral_code' => $this->generateUniqueReferralCode($user),
                    'status' => 'approved',
                    'tax_form_type' => null,
                    'tax_form_data' => [],
                ]);

                setPermissionsTeamId($company->id);
                $user->assignRole('partner');

                return $newPartner;
            });
        }

        // 4. Si YA EXISTE, aplicamos Read-Repair
        $needsUpdate = false;

        if ($partner->status === 'pending') {
            $partner->status = 'approved';
            $needsUpdate = true;
        }

        if (empty($partner->referral_code)) {
            $partner->referral_code = $this->generateUniqueReferralCode($user);
            $needsUpdate = true;
        }

        if (empty($partner->org_seller_type_id) && $sellerTypeId) {
            $partner->org_seller_type_id = $sellerTypeId;
            $needsUpdate = true;
        }

        if (empty($partner->org_partner_tier_id) && $defaultTierId) {
            $partner->org_partner_tier_id = $defaultTierId;
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
