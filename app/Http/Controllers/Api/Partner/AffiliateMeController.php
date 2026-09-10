<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AffiliateMeController extends Controller
{
    public function __invoke(Request $request)
    {
        // 1. Cargamos el usuario con su perfil y la relación 'companies'
        // IMPORTANTE: También cargamos la relación anidada 'company' que está dentro de OrgCompanyUser
        $user = $request->user()->load(['profile', 'companies.company', 'partnerProfile.tier']);

        // 2. Obtenemos el primer registro de la tabla intermedia
        $pivot = $user->companies->first();

        // 3. El UID realmente vive dentro del modelo 'company' que está relacionado al pivot
        $workspaceUid = $pivot && $pivot->company ? $pivot->company->uid : null;

        // 4. Mandamos llamar a tu accessor. Esto devolverá el nivel o null si no tiene propiedades
        $investorTier = $user->current_investor_tier;
        $partnerTier = $user->current_partner_tier;
      $loanTierData = $user->current_loan_tier;
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->profile->avatar_url ?? null,
                'workspace_uid' => $workspaceUid,
                'role' => 'partner',
                // --- Datos exclusivos para Yel Investor ---
                'investor_tier' => $investorTier ? [
                    'name' => $investorTier->name,
                    'color' => $investorTier->color_theme ?? 'gray',
                ] : null,

                // --- Datos exclusivos para Yel Pro (Vendedores) ---
                'lifetime_sales_volume' => (float) $user->lifetime_sales_volume,
                'partner_tier' => $partnerTier ? [
                    'name' => $partnerTier->name,
                    'color' => $partnerTier->color_theme ?? 'gray',
                    'commission_percentage' => (float) $partnerTier->commission_percentage,
                ] : null,

             'loan_tier' => $loanTierData ? [
                    'name' => $loanTierData['tier']->name,
                    'color' => $loanTierData['tier']->color_theme ?? 'emerald',
                    'small_loan_fee' => (float) $loanTierData['tier']->small_loan_fixed_fee,
                    'large_loan_cap' => (float) $loanTierData['tier']->large_loan_cap,
                    'medium_loan_percentage' => (float) $loanTierData['tier']->medium_loan_percentage,
                    'current_volume' => $loanTierData['current_month_volume'],
                    'next_tier_name' => $loanTierData['next_tier_name'],
                    'next_tier_volume' => $loanTierData['next_tier_min_volume'], // Mapeado correctamente con el accessor
                ] : null,
            ],
        ]);
    }
}
