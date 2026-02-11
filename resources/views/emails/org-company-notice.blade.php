<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo aviso | YEL SERVICES</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <span style="display:none; visibility:hidden; color:transparent; height:0; width:0;">
        📢 Nuevo aviso en {{ $notice->company->name }}: {{ $notice->title }}
    </span>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; padding:40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:20px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 15px 35px rgba(30, 41, 59, 0.05);">
                    
                    <tr>
                        <td height="6" style="background:{{ $notice->level->color ?? '#3b82f6' }};"></td>
                    </tr>

                    <tr>
                        <td style="padding:40px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <span style="
                                            display:inline-block;
                                            padding:6px 14px;
                                            font-size:11px;
                                            text-transform:uppercase;
                                            letter-spacing:1px;
                                            font-weight:800;
                                            color:{{ $notice->level->color ?? '#3b82f6' }};
                                            background:{{ ($notice->level->color ?? '#3b82f6') . '15' }};
                                            border: 1px solid {{ ($notice->level->color ?? '#3b82f6') . '30' }};
                                            border-radius:8px;
                                        ">
                                            {{ $notice->level->name ?? 'Aviso General' }}
                                        </span>
                                    </td>
                                    <td align="right" style="font-size:13px; color:#94a3b8; font-weight:500;">
                                        {{ optional($notice->published_at)->format('d M, Y') }}
                                    </td>
                                </tr>
                            </table>

                            <h1 style="margin:25px 0 10px; font-size:26px; line-height:1.3; color:#0f172a; font-weight:800; letter-spacing:-0.5px;">
                                {{ $notice->title }}
                            </h1>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:25px; border-bottom:1px solid #f1f5f9; padding-bottom:20px;">
                                <tr>
                                    <td width="40" style="vertical-align:middle;">
                                        <div style="width:32px; height:32px; background:#e2e8f0; border-radius:50%; text-align:center; line-height:32px; color:#64748b; font-weight:bold; font-size:14px;">
                                            {{ substr($notice->creator->name, 0, 1) }}
                                        </div>
                                    </td>
                                    <td style="font-size:14px; color:#64748b; line-height:1.4;">
                                        Publicado por <strong style="color:#334155;">{{ $notice->creator->profile->first_name ?? $notice->creator->name }}</strong><br>
                                        <span style="font-size:12px; color:#94a3b8;">{{ $notice->company->name }} @if($notice->area) • {{ $notice->area->name }} @endif</span>
                                    </td>
                                </tr>
                            </table>

                            <div style="
                                font-size:16px;
                                line-height:1.8;
                                color:#475569;
                                background:#fbfcfe;
                                padding:25px;
                                border-radius:16px;
                                border:1px solid #f1f5f9;
                            ">
                                {!! nl2br(e($notice->body)) !!}
                            </div>

                            <div style="margin-top:35px; text-align:center;">
                                <a href="{{ config('app.frontend_url') }}/dashboard/{{ $notice->company->uid }}/notices"
                                    style="
                                        display:inline-block;
                                        padding:16px 35px;
                                        background:#1e293b;
                                        color:#ffffff;
                                        text-decoration:none;
                                        border-radius:12px;
                                        font-size:15px;
                                        font-weight:600;
                                        box-shadow:0 10px 20px rgba(30, 41, 59, 0.2);
                                    ">
                                    Ver aviso completo en YEL SERVICES →
                                </a>
                            </div>

                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f8fafc; padding:20px 40px; border-top:1px solid #f1f5f9; text-align:center;">
                            <p style="margin:0; font-size:12px; color:#94a3b8; line-height:1.5;">
                                Este es un aviso automático de <strong>{{ $notice->company->name }}</strong>.<br>
                                Gestionado a través de YEL SERVICES.
                            </p>
                        </td>
                    </tr>
                </table>

                <table width="100%" style="max-width:600px; margin-top:25px;">
                    <tr>
                        <td align="center" style="font-size:12px; color:#cbd5e1;">
                            © {{ date('Y') }} YEL SERVICES LLC. Todos los derechos reservados.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>