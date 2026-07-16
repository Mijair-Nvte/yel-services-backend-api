<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f9fafb; padding: 20px; color: #1f2937; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-top: 4px solid #10b981; }
        .details-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 6px; margin: 24px 0; font-size: 14px; }
        .details-box p { margin: 8px 0; }
        .warning { font-size: 13px; color: #64748b; margin-top: 24px; }
        .footer { margin-top: 32px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Nuevo inicio de sesión en tu cuenta</h2>
        <p>Hola <strong>{{ $userName }}</strong>,</p>
        <p>Te informamos que hemos detectado un nuevo acceso a tu cuenta..</p>
        
        <div class="details-box">
            <p><strong>Fecha y hora:</strong> {{ $time }} (Hora Centro)</p>
            <p><strong>Dirección IP:</strong> {{ $ip ?? 'No disponible' }}</p>
            <p><strong>Navegador / Dispositivo:</strong> {{ $userAgent ?? 'No disponible' }}</p>
        </div>
        
        <p class="warning">
            Si fuiste tú, no tienes que hacer nada. Si no reconoces esta actividad, por favor cambia tu contraseña inmediatamente y contacta al soporte técnico.
        </p>
        
        <div class="footer">
            &copy; {{ date('Y') }} YEL GROUP, LLC. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>