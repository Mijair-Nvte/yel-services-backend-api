<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingController extends Controller
{
    public function contacts(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // 1. Recibir fechas exactas del frontend (preparado para Shadcn Calendar)
        $start = Carbon::parse($request->query('start', now()->startOfMonth()->toDateString()))->startOfDay();
        $end = Carbon::parse($request->query('end', now()->endOfMonth()->toDateString()))->endOfDay();

        // 2. Obtener datos del periodo principal
        $primaryData = $this->getMetricsForPeriod($company->id, $start, $end);

        $response = [
            'primary' => $primaryData,
            'comparison' => null,
        ];

        // 3. Si hay fechas de comparación, generamos el segundo bloque
        if ($request->filled('compare_start') && $request->filled('compare_end')) {
            $compareStart = Carbon::parse($request->query('compare_start'))->startOfDay();
            $compareEnd = Carbon::parse($request->query('compare_end'))->endOfDay();

            $response['comparison'] = $this->getMetricsForPeriod($company->id, $compareStart, $compareEnd);
        }

        return response()->json($response, 200);
    }

    /**
     * Función privada para extraer toda la data de un rango de fechas específico.
     */
    private function getMetricsForPeriod($companyId, $start, $end)
    {
        // Si el rango es mayor a 60 días, agrupamos por mes, si no, por día
        $diffDays = $start->diffInDays($end);
        $groupByFormat = $diffDays > 60 ? '%Y-%m' : '%Y-%m-%d';

        // KPIs
        $newLeadsCount = OrgCustomer::where('org_company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $existingLeadsCount = OrgCustomer::where('org_company_id', $companyId)
            ->whereBetween('updated_at', [$start, $end])
            ->where('created_at', '<', $start)
            ->count();

        $totalContactsCount = $newLeadsCount + $existingLeadsCount;

        // Gráfica de Barras
        $newLeadsTrend = OrgCustomer::where('org_company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->select(DB::raw("DATE_FORMAT(created_at, '{$groupByFormat}') as date"), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->get()->keyBy('date');

        $existingLeadsTrend = OrgCustomer::where('org_company_id', $companyId)
            ->whereBetween('updated_at', [$start, $end])
            ->where('created_at', '<', $start)
            ->select(DB::raw("DATE_FORMAT(updated_at, '{$groupByFormat}') as date"), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->get()->keyBy('date');

        $trendData = [];
        $periodDates = collect(array_merge($newLeadsTrend->keys()->toArray(), $existingLeadsTrend->keys()->toArray()))->unique()->sort();

        foreach ($periodDates as $date) {
            $trendData[] = [
                'name' => $date,
                'newLeads' => $newLeadsTrend->get($date)->total ?? 0,
                'existingLeads' => $existingLeadsTrend->get($date)->total ?? 0,
            ];
        }

        // Gráfica de Pastel (Fuentes)
        $sources = OrgCustomer::where('org_company_id', $companyId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('updated_at', [$start, $end]);
            })
            ->select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.source')) as source_name"), DB::raw('count(*) as total'))
            ->groupBy('source_name')
            ->get();

        $colors = ['#4f46e5', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];
        $pieData = [];
        $colorIndex = 0;

        foreach ($sources as $source) {
            $pieData[] = [
                'name' => $source->source_name ?: 'Desconocido',
                'value' => $source->total,
                'color' => $colors[$colorIndex % count($colors)],
            ];
            $colorIndex++;
        }

        return [
            'label' => $start->format('d M, Y').' - '.$end->format('d M, Y'),
            'kpis' => [
                'total' => $totalContactsCount,
                'new' => $newLeadsCount,
                'existing' => $existingLeadsCount,
            ],
            'trendChart' => array_values($trendData),
            'pieChart' => $pieData,
        ];
    }

    /**
     * Reporte de Eventos (Asistencia, Nuevos vs Existentes, Fuentes)
     */
    public function events(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $start = Carbon::parse($request->query('start', now()->startOfMonth()->toDateString()))->startOfDay();
        $end = Carbon::parse($request->query('end', now()->endOfMonth()->toDateString()))->endOfDay();

        $primaryData = $this->getEventMetricsForPeriod($company->id, $start, $end);

        $response = [
            'primary' => $primaryData,
            'comparison' => null,
        ];

        if ($request->filled('compare_start') && $request->filled('compare_end')) {
            $compareStart = Carbon::parse($request->query('compare_start'))->startOfDay();
            $compareEnd = Carbon::parse($request->query('compare_end'))->endOfDay();
            $response['comparison'] = $this->getEventMetricsForPeriod($company->id, $compareStart, $compareEnd);
        }

        return response()->json($response, 200);
    }

    private function getEventMetricsForPeriod($companyId, $start, $end)
    {
        // 1. Obtener los eventos que suceden en este rango
        $events = \App\Models\OrgEvent::where('org_company_id', $companyId)
            ->whereBetween('starts_at', [$start, $end])
            ->get();

        $eventIds = $events->pluck('id');

        // 2. Obtener todos los registros de esos eventos
        $registrations = \App\Models\OrgEventRegistration::whereIn('org_event_id', $eventIds)->get();

        // 3. Cálculos de KPIs
        $totalEvents = $events->count();
        $totalRegistrations = $registrations->count();
        $attendedCount = $registrations->where('attended', 1)->count();

        $attendanceRate = $totalRegistrations > 0
            ? round(($attendedCount / $totalRegistrations) * 100, 1)
            : 0;

        // 4. Top Eventos (Barras)
        $topEventsData = [];
        foreach ($events as $event) {
            $count = $registrations->where('org_event_id', $event->id)->count();
            if ($count > 0) {
                $topEventsData[] = [
                    'name' => mb_strimwidth($event->title, 0, 15, '...'), // Truncar títulos largos
                    'registrations' => $count,
                ];
            }
        }
        // Ordenar de mayor a menor y tomar los 5 mejores
        usort($topEventsData, fn ($a, $b) => $b['registrations'] <=> $a['registrations']);
        $topEventsData = array_slice($topEventsData, 0, 5);

        // 5. Gráfica: Nuevos vs Existentes (Pie)
        $newCount = $registrations->where('is_new_lead', 1)->count();
        $existingCount = $totalRegistrations - $newCount;
        $leadTypePie = [
            ['name' => 'Nuevos', 'value' => $newCount, 'color' => '#8b5cf6'], // Violet
            ['name' => 'Existentes', 'value' => $existingCount, 'color' => '#10b981'], // Emerald
        ];

        // 6. Gráfica: Asistencia (Pie)
        $missedCount = $totalRegistrations - $attendedCount;
        $attendancePie = [
            ['name' => 'Asistieron', 'value' => $attendedCount, 'color' => '#10b981'], // Emerald
            ['name' => 'No Asistieron', 'value' => $missedCount, 'color' => '#f43f5e'], // Rose
        ];

        // 7. Gráfica: Fuentes de Tráfico (Pie)
        $sourcesData = [];
        $groupedSources = $registrations->groupBy('source');
        $colors = ['#3b82f6', '#f59e0b', '#ec4899', '#0ea5e9', '#84cc16'];
        $i = 0;
        foreach ($groupedSources as $sourceName => $group) {
            $sourcesData[] = [
                'name' => $sourceName ?: 'Directo / Manual',
                'value' => $group->count(),
                'color' => $colors[$i % count($colors)],
            ];
            $i++;
        }

        return [
            'label' => $start->format('d M, Y').' - '.$end->format('d M, Y'),
            'kpis' => [
                'total_events' => $totalEvents,
                'total_registrations' => $totalRegistrations,
                'attendance_rate' => $attendanceRate,
            ],
            'topEventsChart' => $topEventsData,
            'leadTypePie' => $leadTypePie,
            'attendancePie' => $attendancePie,
            'sourcesPie' => $sourcesData,
        ];
    }

    /**
     * 📊 NUEVO: Reporte de Ventas y Referidos (Partners)
     */
    public function sales(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $start = Carbon::parse($request->query('start', now()->startOfMonth()->toDateString()))->startOfDay();
        $end = Carbon::parse($request->query('end', now()->endOfMonth()->toDateString()))->endOfDay();

        $response = ['primary' => $this->getSalesMetricsForPeriod($company->id, $start, $end), 'comparison' => null];

        if ($request->filled('compare_start') && $request->filled('compare_end')) {
            $compareStart = Carbon::parse($request->query('compare_start'))->startOfDay();
            $compareEnd = Carbon::parse($request->query('compare_end'))->endOfDay();
            $response['comparison'] = $this->getSalesMetricsForPeriod($company->id, $compareStart, $compareEnd);
        }

        return response()->json($response, 200);
    }

    private function getSalesMetricsForPeriod($companyId, $start, $end)
    {
        // 1. Obtener todas las ventas pagadas en el periodo
        $sales = DB::table('org_sales')
            ->leftJoin('org_customers', 'org_sales.org_customer_id', '=', 'org_customers.id')
            ->leftJoin('org_services', 'org_sales.org_service_id', '=', 'org_services.id')
            ->where('org_sales.org_company_id', $companyId)
            ->whereBetween('org_sales.created_at', [$start, $end])
            ->where('org_sales.payment_status', 'paid')
            ->select(
                'org_sales.*',
                'org_customers.created_at as customer_created_at',
                'org_services.name as service_name'
            )
            ->get();

        // 2. KPIs Generales
        $totalRevenue = $sales->sum('total_amount');
        $totalSalesCount = $sales->count();

        // Comisiones generadas (Pendientes o Pagadas)
        $totalCommissions = $sales->whereIn('commission_status', ['pending', 'paid'])->sum('commission_amount');

        // 3. Top 5 Servicios más vendidos (Gráfica de Barras)
        $servicesGroup = $sales->groupBy('service_name');
        $topServicesData = [];
        foreach ($servicesGroup as $name => $group) {
            $topServicesData[] = [
                'name' => mb_strimwidth($name ?: 'Servicio Personalizado', 0, 15, '...'),
                'revenue' => (float) $group->sum('total_amount'),
            ];
        }
        usort($topServicesData, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $topServicesData = array_slice($topServicesData, 0, 5);

        // 4. Desglose de Clientes (Nuevos vs Existentes)
        $newCount = 0;
        $existingCount = 0;
        foreach ($sales as $sale) {
            if ($sale->customer_created_at && Carbon::parse($sale->customer_created_at)->gte($start)) {
                $newCount++;
            } else {
                $existingCount++;
            }
        }
        $customerTypePie = [
            ['name' => 'Nuevos', 'value' => $newCount, 'color' => '#3b82f6'],
            ['name' => 'Existentes', 'value' => $existingCount, 'color' => '#0ea5e9'],
        ];

        // 5. Top Partners / Vendedores (Métrica de referidos)
        $topPartners = DB::table('org_sales')
            ->join('users', 'org_sales.seller_id', '=', 'users.id')
            ->where('org_sales.org_company_id', $companyId)
            ->whereBetween('org_sales.created_at', [$start, $end])
            ->where('org_sales.payment_status', 'paid')
            ->select(
                'users.name',
                DB::raw('SUM(org_sales.total_amount) as total_sales'),
                DB::raw('SUM(org_sales.commission_amount) as total_commissions')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        return [
            'label' => $start->format('d M, Y').' - '.$end->format('d M, Y'),
            'kpis' => [
                'total_revenue' => $totalRevenue,
                'total_sales' => $totalSalesCount,
                'total_commissions' => $totalCommissions,
            ],
            'topServicesChart' => $topServicesData,
            'customerTypePie' => $customerTypePie,
            'topPartners' => $topPartners,
        ];
    }



    /**
     * 📊 Reporte de Préstamos (Loans)
     */
    public function loans(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        
        $start = Carbon::parse($request->query('start', now()->startOfMonth()->toDateString()))->startOfDay();
        $end = Carbon::parse($request->query('end', now()->endOfMonth()->toDateString()))->endOfDay();
        
        $response = ['primary' => $this->getLoansMetricsForPeriod($company->id, $start, $end), 'comparison' => null];

        if ($request->filled('compare_start') && $request->filled('compare_end')) {
            $compareStart = Carbon::parse($request->query('compare_start'))->startOfDay();
            $compareEnd = Carbon::parse($request->query('compare_end'))->endOfDay();
            $response['comparison'] = $this->getLoansMetricsForPeriod($company->id, $compareStart, $compareEnd);
        }

        return response()->json($response, 200);
    }

    private function getLoansMetricsForPeriod($companyId, $start, $end)
    {
        // 1. Obtener todas las aplicaciones de préstamo en el periodo
        $loans = DB::table('org_loan_applications')
            ->where('org_company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        // 2. KPIs Generales
        $totalApplications = $loans->count();
        $totalEstimatedVolume = $loans->sum('estimated_amount');
        $wonApplicationsCount = $loans->where('status', 'Won')->count();
        $totalCommissions = $loans->whereIn('commission_status', ['pending', 'paid'])->sum('commission_amount');

        // 3. Gráfica de Pastel: Estado del Pipeline (Open, Won, Lost, Abandon)
        $statusGroup = $loans->groupBy('status');
        $statusColors = [
            'Open' => '#3b82f6',    // Blue
            'Won' => '#10b981',     // Emerald
            'Lost' => '#f43f5e',    // Rose
            'Abandon' => '#94a3b8'  // Slate
        ];
        
        $pipelinePie = [];
        foreach($statusGroup as $status => $group) {
            $pipelinePie[] = [
                'name' => $status,
                'value' => $group->count(),
                'color' => $statusColors[$status] ?? '#cbd5e1'
            ];
        }

        // 4. Top Tipos de Préstamo por Volumen (Gráfica de Barras)
        $typeGroup = $loans->groupBy('loan_type');
        $loanTypesData = [];
        foreach($typeGroup as $type => $group) {
            $loanTypesData[] = [
                'name' => mb_strimwidth($type ?: 'No especificado', 0, 15, '...'),
                'volume' => (float) $group->sum('estimated_amount'),
                'count' => $group->count()
            ];
        }
        usort($loanTypesData, fn($a, $b) => $b['volume'] <=> $a['volume']);
        $loanTypesData = array_slice($loanTypesData, 0, 5); // Top 5

        return [
            'label' => $start->format('d M, Y') . ' - ' . $end->format('d M, Y'),
            'kpis' => [
                'total_applications' => $totalApplications,
                'total_volume' => $totalEstimatedVolume,
                'won_applications' => $wonApplicationsCount,
                'total_commissions' => $totalCommissions,
            ],
            'pipelinePie' => $pipelinePie,
            'loanTypesChart' => $loanTypesData
        ];
    }




    /**
     * 📊 Reporte de Seguros (Insurance)
     */
    public function insurance(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        
        $start = Carbon::parse($request->query('start', now()->startOfMonth()->toDateString()))->startOfDay();
        $end = Carbon::parse($request->query('end', now()->endOfMonth()->toDateString()))->endOfDay();
        
        $response = ['primary' => $this->getInsuranceMetricsForPeriod($company->id, $start, $end), 'comparison' => null];

        if ($request->filled('compare_start') && $request->filled('compare_end')) {
            $compareStart = Carbon::parse($request->query('compare_start'))->startOfDay();
            $compareEnd = Carbon::parse($request->query('compare_end'))->endOfDay();
            $response['comparison'] = $this->getInsuranceMetricsForPeriod($company->id, $compareStart, $compareEnd);
        }

        return response()->json($response, 200);
    }

    private function getInsuranceMetricsForPeriod($companyId, $start, $end)
    {
        // 1. Obtener todas las pólizas/aplicaciones en el periodo
        $insurances = DB::table('org_insurance_applications')
            ->where('org_company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        // 2. KPIs Generales
        $totalApplications = $insurances->count();
        $wonApplicationsCount = $insurances->where('status', 'Won')->count();
        $totalCommissions = $insurances->whereIn('commission_status', ['pending', 'paid'])->sum('commission_amount');
        
        $conversionRate = $totalApplications > 0 
            ? round(($wonApplicationsCount / $totalApplications) * 100, 1) 
            : 0;

        // 3. Gráfica de Pastel: Estado del Pipeline
        $statusGroup = $insurances->groupBy('status');
        $statusColors = [
            'Open' => '#8b5cf6',    // Violet
            'Won' => '#10b981',     // Emerald
            'Lost' => '#f43f5e',    // Rose
            'Abandon' => '#94a3b8'  // Slate
        ];
        
        $pipelinePie = [];
        foreach($statusGroup as $status => $group) {
            $pipelinePie[] = [
                'name' => $status,
                'value' => $group->count(),
                'color' => $statusColors[$status] ?? '#cbd5e1'
            ];
        }

        // 4. Top Tipos de Seguros (Gráfica de Barras por Cantidad de Aplicaciones)
        $typeGroup = $insurances->groupBy('insurance_type');
        $insuranceTypesData = [];
        foreach($typeGroup as $type => $group) {
            $insuranceTypesData[] = [
                'name' => mb_strimwidth($type ?: 'No especificado', 0, 15, '...'),
                'count' => $group->count()
            ];
        }
        // Ordenar por cantidad de pólizas (mayor a menor)
        usort($insuranceTypesData, fn($a, $b) => $b['count'] <=> $a['count']);
        $insuranceTypesData = array_slice($insuranceTypesData, 0, 5); // Top 5

        return [
            'label' => $start->format('d M, Y') . ' - ' . $end->format('d M, Y'),
            'kpis' => [
                'total_applications' => $totalApplications,
                'won_applications' => $wonApplicationsCount,
                'conversion_rate' => $conversionRate,
                'total_commissions' => $totalCommissions,
            ],
            'pipelinePie' => $pipelinePie,
            'insuranceTypesChart' => $insuranceTypesData
        ];
    }


 /**
     * 🤖 Generar Resumen y Sugerencias con Inteligencia Artificial
     */
    public function generateAiInsights(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        
        $start = Carbon::parse($request->query('start', now()->startOfMonth()->toDateString()))->startOfDay();
        $end = Carbon::parse($request->query('end', now()->endOfMonth()->toDateString()))->endOfDay();
        
        // 1. Recopilar la información de TODAS las secciones
        $primaryData = [
            'periodo' => $start->format('d M, Y') . ' - ' . $end->format('d M, Y'),
            'contactos_y_leads' => $this->getMetricsForPeriod($company->id, $start, $end)['kpis'],
            'eventos_y_asistencia' => $this->getEventMetricsForPeriod($company->id, $start, $end)['kpis'],
            'ventas_y_partners' => $this->getSalesMetricsForPeriod($company->id, $start, $end)['kpis'],
            'prestamos_e_hipotecas' => $this->getLoansMetricsForPeriod($company->id, $start, $end)['kpis'], 
            'seguros_y_polizas' => $this->getInsuranceMetricsForPeriod($company->id, $start, $end)['kpis'], 
        ];

        // 2. Recopilar comparativa si existe
        $comparisonData = null;
        if ($request->filled('compare_start') && $request->filled('compare_end')) {
            $compareStart = Carbon::parse($request->query('compare_start'))->startOfDay();
            $compareEnd = Carbon::parse($request->query('compare_end'))->endOfDay();
            
            $comparisonData = [
                'periodo' => $compareStart->format('d M, Y') . ' - ' . $compareEnd->format('d M, Y'),
                'contactos_y_leads' => $this->getMetricsForPeriod($company->id, $compareStart, $compareEnd)['kpis'],
                'eventos_y_asistencia' => $this->getEventMetricsForPeriod($company->id, $compareStart, $compareEnd)['kpis'],
                'ventas_y_partners' => $this->getSalesMetricsForPeriod($company->id, $compareStart, $compareEnd)['kpis'],
                'prestamos_e_hipotecas' => $this->getLoansMetricsForPeriod($company->id, $compareStart, $compareEnd)['kpis'],
                'seguros_y_polizas' => $this->getInsuranceMetricsForPeriod($company->id, $compareStart, $compareEnd)['kpis'],
            ];
        }

        // 3. Prompt Estricto: Obligamos a la IA a recorrer las 5 áreas
        $systemPrompt = "Eres un Chief Revenue Officer (CRO) experto analizando datos para YEL Group. 
        Tu objetivo es analizar los KPIs operativos y financieros proporcionados en formato JSON y entregar un resumen ejecutivo brillante.
        
        Reglas ESTRICTAS de formato y contenido:
        - Usa un tono gerencial, motivador y directo.
        - Usa formato Markdown (negritas, listas, emojis).
        - No repitas los números de forma aburrida; resalta porcentajes de crecimiento, caídas o conversiones clave.
        - DEBES analizar y mencionar explícitamente las 5 áreas del negocio: Contactos, Eventos, Ventas, Préstamos y Seguros. No omitas ninguna.
        
        Estructura obligatoria de tu respuesta:
        1. 📊 **Resumen Ejecutivo**: Un párrafo corto con la conclusión principal del periodo.
        2. 📈 **Análisis por Área**: Un desglose rápido de lo más destacable en:
           - Contactos y Leads
           - Eventos (Asistencia)
           - Ventas y Comisiones
           - Préstamos e Hipotecas (Pipeline y volumen)
           - Seguros (Pólizas y conversión)
        3. 💡 **3 Sugerencias Estratégicas**: Acciones claras y ejecutables para mejorar las áreas con menor rendimiento basadas en los datos actuales.";

        $userData = "Analiza los siguientes datos de rendimiento:\n\n" . 
                    "DATOS ACTUALES:\n" . json_encode($primaryData, JSON_PRETTY_PRINT);
        
        if ($comparisonData) {
            $userData .= "\n\nDATOS DEL PERIODO ANTERIOR (COMPARATIVA):\n" . json_encode($comparisonData, JSON_PRETTY_PRINT);
        }

        try {
            $response = \OpenAI\Laravel\Facades\OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userData],
                ],
                'temperature' => 0.4, 
                'max_tokens' => 1000, 
            ]);

            return response()->json([
                'success' => true,
                'insights' => $response->choices[0]->message->content
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el análisis de IA: ' . $e->getMessage()
            ], 500);
        }
    }
}
