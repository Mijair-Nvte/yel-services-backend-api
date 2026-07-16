<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\OrgSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class PartnerDashboardController extends Controller
{
    /**
     * Obtiene todas las estadísticas, ventas recientes y datos de gráficas para el Afiliado.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // 1. Obtener todos los códigos de referido asociados a este usuario
            $referralCodes = DB::table('org_company_partners')
                ->where('user_id', $user->id)

                ->pluck('referral_code')
                ->toArray();

            // 2. Construir la consulta base de ventas del afiliado
            $salesQuery = OrgSale::query()
                ->where(function ($query) use ($user, $referralCodes) {
                    $query->where('seller_id', $user->id);
                    if (!empty($referralCodes)) {
                        $query->orWhereIn('referral_code', $referralCodes);
                    }
                });

            // 3. Aplicar filtros dinámicos (Rango de fechas, Año, Mes, Día)
            $salesQuery = $this->applyFilters($salesQuery, $request);

            // --- PASO A: CALCULAR MÉTRICAS GENERALES ---
            $allFilteredSales = (clone $salesQuery)->get();

            $stats = [
                'total_sales_count'     => $allFilteredSales->count(),
                'completed_sales_count' => $allFilteredSales->where('payment_status', 'paid')->count(),
                'total_commissions'     => round($allFilteredSales->sum('commission_amount'), 2),
                'paid_commissions'      => round($allFilteredSales->where('commission_status', 'paid')->sum('commission_amount'), 2),
                'pending_commissions'   => round($allFilteredSales->where('commission_status', 'pending')->sum('commission_amount'), 2),
            ];

            // --- PASO B: VENTAS RECIENTES ---
            // Traemos las últimas 5 ventas con la información del cliente
            $recentSales = (clone $salesQuery)
                ->with('customer:id,first_name,last_name,email')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($sale) {
                    return [
                        'uid'               => $sale->uid,
                        'product_name'      => $sale->product_name ?? 'Servicio',
                        'total_amount'      => $sale->total_amount,
                        'payment_status'    => $sale->payment_status,
                        'commission_amount' => $sale->commission_amount,
                        'commission_status' => $sale->commission_status,
                        'customer'          => $sale->customer ? [
                            'name'  => trim($sale->customer->first_name . ' ' . $sale->customer->last_name),
                            'email' => $sale->customer->email
                        ] : null,
                        'date'              => $sale->created_at->toIso8601String(),
                    ];
                });

            // --- PASO C: DATOS PARA LA GRÁFICA ---
            $chartData = $this->generateChartData($salesQuery, $request);

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully.',
                'data'    => [
                    'stats'        => $stats,
                    'recent_sales' => $recentSales,
                    'chart'        => $chartData
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in PartnerDashboardController: ' . $e->getMessage(), [
                'user_id' => auth()->id() ?? 'Guest',
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar las estadísticas del dashboard.',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Aplica los filtros de fecha según los parámetros recibidos.
     */
    private function applyFilters($query, Request $request)
    {
        if ($request->has('start_date') && $request->has('end_date')) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();
            return $query->whereBetween('created_at', [$start, $end]);
        }

        if ($request->has('year')) {
            $query->whereYear('created_at', $request->year);
        }

        if ($request->has('month')) {
            $query->whereMonth('created_at', $request->month);
        }

        if ($request->has('day')) {
            $query->whereDay('created_at', $request->day);
        }

        return $query;
    }

    /**
     * Agrupa las ventas para construir los puntos de la gráfica.
     */
    private function generateChartData($query, Request $request)
    {
        // Si se filtra por un mes específico, agrupamos por DÍA.
        if ($request->has('month')) {
            return $query->select(
                    DB::raw('DATE(created_at) as period'),
                    DB::raw('COUNT(*) as sales_count'),
                    DB::raw('SUM(commission_amount) as total_commissions')
                )
                ->groupBy('period')
                ->orderBy('period', 'asc')
                ->get();
        }

        // Agrupación por MES por defecto
        return $query->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as period'),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(commission_amount) as total_commissions')
            )
            ->groupBy('period')
            ->orderBy('period', 'asc')
            ->get();
    }
}