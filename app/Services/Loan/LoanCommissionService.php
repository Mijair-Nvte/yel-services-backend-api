<?php

namespace App\Services\Loan;

use App\Models\OrgLoanApplication;
use App\Models\OrgLoanTier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanCommissionService
{
    /**
     * Calcula y actualiza las comisiones para todos los préstamos del mes de un partner
     * aplicando la regla de nivel retroactivo.
     */
    public function recalculateForApplication(OrgLoanApplication $triggerApplication): void
    {
        // 1. Determinar el mes basado en la fecha en que se ganó (won_at)
        $date = Carbon::parse($triggerApplication->won_at ?? now());
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        DB::transaction(function () use ($triggerApplication, $startOfMonth, $endOfMonth) {
            // 2. Obtener TODAS las solicitudes 'Won' de este partner en este mes
            $monthlyLoans = OrgLoanApplication::where('org_company_id', $triggerApplication->org_company_id)
                ->where('user_id', $triggerApplication->user_id)
                ->where('status', 'Won')
                ->whereBetween('won_at', [$startOfMonth, $endOfMonth])
                ->get();

            // 3. Sumar el volumen total fondeado del mes
            $totalVolume = $monthlyLoans->sum('estimated_amount');

            // 4. Buscar el nivel (Tier) al que pertenece este volumen
            $tier = OrgLoanTier::where('org_company_id', $triggerApplication->org_company_id)
                ->where('is_active', true)
                ->where('min_monthly_volume', '<=', $totalVolume)
                ->where(function ($query) use ($totalVolume) {
                    $query->whereNull('max_monthly_volume')
                          ->orWhere('max_monthly_volume', '>=', $totalVolume);
                })
                ->first();

            if (!$tier) {
                return; // Si no hay nivel configurado, salimos.
            }

            // 5. Recalcular la comisión para TODOS los préstamos del mes (Regla Retroactiva)
            foreach ($monthlyLoans as $loan) {
                $commission = $this->calculateCommissionAmount($loan->estimated_amount, $tier);
                
                // La regla indica: "El recálculo solo puede subir tu Fee, nunca bajarlo"
                if ($commission > $loan->commission_amount) {
                    $loan->update([
                        'commission_amount' => $commission,
                        'commission_status' => $loan->commission_status ?? 'pending',
                    ]);
                }
            }
        });
    }

    /**
     * Aplica la fórmula del nivel al monto de un préstamo individual.
     */
    public function calculateCommissionAmount(float $amount, OrgLoanTier $tier): float
    {
        // Préstamo Chico
        if ($amount <= $tier->small_loan_max_amount) {
            return $tier->small_loan_fixed_fee;
        }

        // Préstamo Grande
        if ($amount >= $tier->large_loan_min_amount) {
            $calculated = $amount * $tier->medium_loan_percentage;
            // Retorna el valor calculado o el tope, el que sea menor
            return min($calculated, $tier->large_loan_cap);
        }

        // Préstamo Mediano (Entre chico y grande)
        return $amount * $tier->medium_loan_percentage;
    }
}