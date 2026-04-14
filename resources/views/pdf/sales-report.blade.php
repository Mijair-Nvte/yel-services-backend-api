<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #1e293b; font-size: 24px; }
        .header p { margin: 5px 0 0; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #f8fafc; color: #475569; font-weight: bold; text-align: left; padding: 10px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 10px; border-bottom: 1px solid #e2e8f0; color: #334155; }
        
        /* Estatus */
        .status-paid { color: #16a34a; font-weight: bold; }
        .status-pending { color: #d97706; font-weight: bold; }
        .date-subtext { font-size: 10px; color: #64748b; display: block; margin-top: 3px; } /* <--- Nuevo estilo para la fecha */
        
        .totals { margin-top: 30px; width: 50%; float: right; }
        .totals-table th { background-color: white; border: none; text-align: right; }
        .totals-table td { font-size: 16px; font-weight: bold; text-align: right; border-bottom: 2px solid #cbd5e1; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Comisiones y Ventas</h1>
        <p>Generado el: {{ $fechaReporte }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha Venta</th>
                <th>Cliente / Servicio</th>
                <th>Monto Bruto</th>
                <th>Vendedor</th>
                <th>Comisión</th>
                <th>Estatus</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
            <tr>
                <td>{{ \Carbon\Carbon::parse($sale->created_at)->format('d/m/Y') }}</td>
                <td>
                    <strong>{{ $sale->customer_name ?? 'Cliente Desconocido' }}</strong><br>
                    <span style="font-size: 10px; color: #64748b;">{{ $sale->product_name }}</span>
                </td>
                <td>${{ number_format($sale->total_amount, 2) }}</td>
                <td>{{ $sale->seller ? $sale->seller->name : 'N/A' }}</td>
                <td>${{ number_format($sale->commission_amount, 2) }}</td>
                <td>
                    @if($sale->commission_status === 'paid')
                        <span class="status-paid">Pagada</span>
                        @if($sale->seller_payout_date)
                            <span class="date-subtext">{{ \Carbon\Carbon::parse($sale->seller_payout_date)->format('d/m/Y') }}</span>
                        @endif
                        
                    @elseif($sale->commission_status === 'pending')
                        <span class="status-pending">Pendiente</span>
                        @if($sale->seller_payout_date)
                            <span class="date-subtext">Pagar el: {{ \Carbon\Carbon::parse($sale->seller_payout_date)->format('d/m/Y') }}</span>
                        @endif
                        
                    @else
                        N/A
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table class="totals-table">
            <tr>
                <th>Total Ventas (Bruto):</th>
                <td>${{ number_format($totalAmount, 2) }}</td>
            </tr>
            <tr>
                <th>Total Comisiones:</th>
                <td style="color: #16a34a;">${{ number_format($totalCommissions, 2) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>