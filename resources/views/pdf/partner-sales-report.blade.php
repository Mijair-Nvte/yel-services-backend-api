<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Mis Comisiones</title>
    <style>
        /* Tipografía y Base */
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #333; margin: 20px; }
        /* ... (USA EXACTAMENTE EL MISMO CSS DE TU ARCHIVO ANTERIOR HASTA EL BODY) ... */
        .header-container { width: 100%; margin-bottom: 30px; border-bottom: 3px solid #1a1a1a; padding-bottom: 15px; }
        .logo { width: 180px; float: left; }
        .header-info { text-align: right; margin-top: 10px; }
        .header-info h1 { margin: 0; color: #1a1a1a; font-size: 20px; text-transform: uppercase; }
        .header-info p { margin: 5px 0 0; color: #64748b; font-size: 12px; }
        .clearfix::after { content: ""; clear: both; display: table; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #1a1a1a; color: #ffffff; font-weight: bold; text-align: left; padding: 12px 10px; text-transform: uppercase; font-size: 10px; }
        td { padding: 10px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .status-paid { color: #10b981; font-weight: bold; } /* Cambiado a verde para que coincida con la web */
        .status-pending { color: #d97706; font-weight: bold; }
        .date-subtext { font-size: 9px; color: #94a3b8; display: block; margin-top: 2px; }
        .product-name { font-size: 10px; color: #64748b; }
        .totals-section { width: 100%; margin-top: 20px; }
        .totals-table { width: 300px; float: right; }
        .totals-table th { background-color: transparent; color: #475569; text-align: right; border: none; padding: 5px; }
        .totals-table td { font-size: 14px; font-weight: bold; text-align: right; border-bottom: 2px solid #1a1a1a; padding: 5px; }
        .highlight-total { color: #10b981; } 
    </style>
</head>
<body>

    <div class="header-container clearfix">
        <div class="logo">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo" style="width: 100%;">
            @else
                <h2 style="color: #1a1a1a;">{{ $company->name }}</h2>
            @endif
        </div>
        <div class="header-info">
            <h1>Reporte de Mis Comisiones</h1>
            <p>Socio Afiliado: <strong>{{ $partner->name }}</strong></p>
            <p>Fecha de emisión: <strong>{{ $fechaReporte }}</strong></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha Venta</th>
                <th>Cliente / Servicio</th>
                <th>Monto Bruto</th>
                <th>Tu Comisión</th>
                <th>Estatus</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
            <tr>
                <td>{{ \Carbon\Carbon::parse($sale->created_at)->format('d/m/Y') }}</td>
                <td>
                    <strong>{{ $sale->customer_name ?? 'Cliente Desconocido' }}</strong><br>
                    <span class="product-name">{{ $sale->product_name }}</span>
                </td>
                <td>${{ number_format($sale->total_amount, 2) }}</td>
                <td style="font-weight: bold;">${{ number_format($sale->commission_amount, 2) }}</td>
                <td>
                    @if($sale->commission_status === 'paid')
                        <span class="status-paid">Pagada</span>
                        @if($sale->seller_payout_date)
                            <span class="date-subtext">{{ \Carbon\Carbon::parse($sale->seller_payout_date)->format('d/m/Y') }}</span>
                        @endif
                    @elseif($sale->commission_status === 'pending')
                        <span class="status-pending">Pendiente</span>
                        @if($sale->seller_payout_date)
                            <span class="date-subtext">Pagar: {{ \Carbon\Carbon::parse($sale->seller_payout_date)->format('d/m/Y') }}</span>
                        @endif
                    @else
                        <span style="color: #94a3b8;">N/A</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-section clearfix">
        <table class="totals-table">
            <tr>
                <th>VOLUMEN GENERADO:</th>
                <td>${{ number_format($totalAmount, 2) }}</td>
            </tr>
            <tr>
                <th>MIS COMISIONES:</th>
                <td class="highlight-total">${{ number_format($totalCommissions, 2) }}</td>
            </tr>
        </table>
    </div>

</body>
</html>