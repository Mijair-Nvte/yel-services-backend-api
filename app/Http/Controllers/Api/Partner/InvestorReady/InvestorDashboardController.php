<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use App\Models\OrgSale;
use App\Models\OrgProperty; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class InvestorDashboardController extends Controller
{
    /**
     * Obtiene todas las estadísticas, ventas recientes, gráficas y propiedades para el Yel Investor.
     */
    public function index(Request $request, $companyUid = null)
    {
        try {
            $user = $request->user();

            // 1. Obtener la compañía actual para aislar los datos (si viene en la ruta)
            $company = $companyUid ? \App\Models\OrgCompany::where('uid', $companyUid)->first() : null;

            // 2. Construir la consulta base de ventas (sin código de referido)
            // Filtramos donde el usuario sea el 'seller_id' o donde sea el cliente de la compra (customer->user_id)
            $salesQuery = OrgSale::query()
                ->where(function ($query) use ($user) {
                    $query->where('seller_id', $user->id)
                          ->orWhereHas('customer', function ($q) use ($user) {
                              $q->where('user_id', $user->id);
                          });
                });

            if ($company) {
                $salesQuery->where('org_company_id', $company->id);
            }

            // 3. Aplicar filtros dinámicos (Rango de fechas, Año, Mes, Día)
            $salesQuery = $this->applyFilters($salesQuery, $request);

            // --- PASO A: CALCULAR MÉTRICAS GENERALES ---
            $allFilteredSales = (clone $salesQuery)->get();

            // --- PASO B: OBTENER PROPIEDADES (NUEVO) ---
            $propertiesQuery = OrgProperty::where('user_id', $user->id);
            if ($company) {
                $propertiesQuery->where('org_company_id', $company->id);
            }
            $userProperties = $propertiesQuery->get(); // Traemos todos los registros

            $stats = [
                'total_sales_count'     => $allFilteredSales->count(),
                'completed_sales_count' => $allFilteredSales->where('payment_status', 'paid')->count(),
                'total_commissions'     => round($allFilteredSales->sum('commission_amount'), 2),
                'paid_commissions'      => round($allFilteredSales->where('commission_status', 'paid')->sum('commission_amount'), 2),
                'pending_commissions'   => round($allFilteredSales->where('commission_status', 'pending')->sum('commission_amount'), 2),
                'total_properties'      => $userProperties->count(), // ✅ Cantidad de propiedades
            ];

            // --- PASO C: VENTAS RECIENTES ---
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

            // --- PASO D: DATOS PARA LA GRÁFICA ---
            $chartData = $this->generateChartData($salesQuery, $request);

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully.',
                'data'    => [
                    'stats'        => $stats,
                    'recent_sales' => $recentSales,
                    'chart'        => $chartData,
                    'properties'   => $userProperties // ✅ Se envían todos los registros de org_properties del usuario
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in InvestorDashboardController: ' . $e->getMessage(), [
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