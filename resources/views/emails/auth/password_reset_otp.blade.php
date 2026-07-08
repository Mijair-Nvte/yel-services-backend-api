<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f9fafb; padding: 20px; color: #1f2937; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .logo { font-size: 24px; font-weight: bold; color: #2563eb; margin-bottom: 24px; text-align: center; }
        .otp-box { background: #f3f4f6; padding: 16px; text-align: center; font-size: 32px; letter-spacing: 8px; font-weight: bold; border-radius: 6px; margin: 24px 0; color: #111827; }
        .footer { margin-top: 32px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">YEL GROUP</div>
        <p>Hola <strong>{{ $userName }}</strong>,</p>
        <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta. Por favor, ingresa el siguiente código de seguridad en la aplicación para continuar:</p>
        
        <div class="otp-box">
            {{ $otp }}
        </div>
        
        <p>Este código <strong>expirará en 15 minutos</strong> por motivos de seguridad. Si tú no solicitaste un restablecimiento de contraseña, puedes ignorar este correo de forma segura. Tu cuenta seguirá protegida.</p>
        
        <div class="footer">
            &copy; {{ date('Y') }} YEL Group. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>