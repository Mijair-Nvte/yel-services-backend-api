<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Asistentes</title>
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
            width: 180px;
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

        .event-title {
            color: #4f46e5; /* Indigo */
            font-weight: bold;
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
            vertical-align: top;
        }

        tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Estatus y Detalles */
        .status-attended { 
            color: #10b981; /* Emerald */
            font-weight: bold; 
        }

        .status-registered { 
            color: #f59e0b; /* Amber */
            font-weight: bold; 
        }

        .status-cancelled {
            color: #ef4444; /* Red */
            font-weight: bold;
        }

        .contact-info { 
            font-size: 9px; 
            color: #64748b; 
            display: block; 
            margin-top: 2px; 
        }

        .notes-text {
            font-size: 9px;
            color: #94a3b8;
            max-width: 150px;
        }

        /* Totales / KPIs */
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

        .highlight-attended {
            color: #10b981;
        }
    </style>
</head>
<body>

    <div class="header-container clearfix">
        <div class="logo">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo" style="width: 100%;">
            @else
                <h2 style="color: #4f46e5;">{{ $company->name }}</h2>
            @endif
        </div>
        <div class="header-info">
            <h1>Reporte de Asistentes</h1>
            <p>Evento: <span class="event-title">{{ $event->title }}</span></p>
            <p>Fecha de emisión: <strong>{{ $fechaReporte }}</strong></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha Registro</th>
                <th>Asistente / Contacto</th>
                <th>Origen</th>
                <th>Seguimiento</th>
                <th>Asistencia</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            @foreach($registrations as $reg)
            <tr>
                <td>{{ \Carbon\Carbon::parse($reg->created_at)->format('d/m/Y H:i') }}</td>
                <td>
                    <strong>{{ $reg->customer ? trim($reg->customer->first_name . ' ' . $reg->customer->last_name) : 'Desconocido' }}</strong><br>
                    <span class="contact-info">{{ $reg->customer->email ?? 'Sin correo' }}</span>
                    <span class="contact-info">{{ $reg->customer->phone ?? 'Sin teléfono' }}</span>
                </td>
                <td>{{ $reg->source ?? 'Orgánico' }}</td>
                <td>{{ $reg->contact_status ?? 'Sin contactar' }}</td>
              <td>
                    @if($reg->attended)
                        <span class="status-attended">Asistió</span>
                    @else
                        <span class="status-registered">Pendiente</span>
                    @endif
                </td>
                <td class="notes-text">
                    {{ $reg->notes ? \Illuminate\Support\Str::limit($reg->notes, 60) : '--' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

  <div class="totals-section clearfix">
        <table class="totals-table">
            <tr>
                <th>TOTAL REGISTRADOS:</th>
                <td>{{ count($registrations) }}</td>
            </tr>
            <tr>
                <th>ASISTENCIAS CONFIRMADAS:</th>
                <td class="highlight-attended">{{ $registrations->where('attended', true)->count() }}</td>
            </tr>
        </table>
    </div>

</body>
</html>