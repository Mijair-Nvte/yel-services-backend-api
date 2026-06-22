<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        /* Tipografía y Base */
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            font-size: 11px; 
            color: #333; 
            margin: 20px;
        }

        /* Contenedor de Encabezado */
        .header-container {
            width: 100%;
            margin-bottom: 30px;
            border-bottom: 3px solid #1a1a1a;
            padding-bottom: 15px;
        }

        .logo {
            width: 180px; /* Ajusta según prefieras */
            float: left;
        }

        .header-info {
            text-align: right;
            margin-top: 10px;
        }

        .header-info h1 {
            margin: 0;
            color: #1a1a1a;
            font-size: 20px;
            text-transform: uppercase;
        }

        .header-info p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
        }

        /* Limpiar floats */
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Estilos de Tabla */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
        }

        th { 
            background-color: #1a1a1a; 
            color: #ffffff; 
            font-weight: bold; 
            text-align: left; 
            padding: 12px 10px; 
            text-transform: uppercase;
            font-size: 10px;
        }

        td { 
            padding: 10px; 
            border-bottom: 1px solid #e2e8f0; 
            color: #334155; 
            vertical-align: middle;
        }

        tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Estatus y Detalles */
        .status-paid { 
            color: #e91e63; /* Rosa del logo */
            font-weight: bold; 
        }

        .status-pending { 
            color: #d97706; 
            font-weight: bold; 
        }

        .date-subtext { 
            font-size: 9px; 
            color: #94a3b8; 
            display: block; 
            margin-top: 2px; 
        }

        .product-name {
            font-size: 10px; 
            color: #64748b;
        }

        /* Totales */
        .totals-section {
            width: 100%;
            margin-top: 20px;
        }

        .totals-table { 
            width: 300px; 
            float: right; 
        }

        .totals-table th { 
            background-color: transparent; 
            color: #475569; 
            text-align: right; 
            border: none;
            padding: 5px;
        }

        .totals-table td { 
            font-size: 14px; 
            font-weight: bold; 
            text-align: right; 
            border-bottom: 2px solid #1a1a1a;
            padding: 5px;
        }

        .highlight-total {
            color: #e91e63; /* Rosa del logo */
        }
    </style>
</head>
<body>

    <div class="header-container clearfix">
      <div class="logo">
    @if($logoBase64)
        <img src="{{ $logoBase64 }}" alt="Logo" style="width: 100%;">
    @else
        <h2 style="color: #e91e63;">{{ $company->name }}</h2>
    @endif
</div>
        <div class="header-info">
            <h1>Reporte de Comisiones</h1>
            <p>Fecha de emisión: <strong>{{ $fechaReporte }}</strong></p>
        </div>
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
    <strong>{{ $sale->customer ? trim($sale->customer->first_name . ' ' . $sale->customer->last_name) : 'Cliente Desconocido' }}</strong><br>
    <span class="product-name">{{ $sale->product_name }}</span>
</td>
                <td>${{ number_format($sale->total_amount, 2) }}</td>
                <td>{{ $sale->seller ? $sale->seller->name : 'N/A' }}</td>
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
                <th>TOTAL VENTAS:</th>
                <td>${{ number_format($totalAmount, 2) }}</td>
            </tr>
            <tr>
                <th>TOTAL COMISIONES:</th>
                <td class="highlight-total">${{ number_format($totalCommissions, 2) }}</td>
            </tr>
        </table>
    </div>

</body>
</html>