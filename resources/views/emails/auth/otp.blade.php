<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Inicio de Sesión</title>
</head>
<body style="margin: 0; padding: 0; background-color: #EDEAE3;">

<div class="em-wrap">
  <div class="em-frame">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#EDEAE3;font-family:'Work Sans',Arial,sans-serif">
    <tr><td align="center" style="padding:36px 16px">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:10px;overflow:hidden">

      <!-- Encabezado con Logo y Etiqueta -->
      <tr><td style="background:#0E1B29;padding:22px 36px">
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
          <td>
            <img src="{{ url('assets/img/YEL_Group_White.png') }}" alt="YEL GROUP" width="120" style="display:block; max-width: 120px; height: auto;">
          </td>
          <td align="right">
            <span style="font-size:.68rem;font-weight:600;color:#EAB308;letter-spacing:.06em;text-transform:uppercase">🔐 Inicio de Sesión</span>
          </td>
        </tr></table>
      </td></tr>

      <!-- Mensaje Principal -->
      <tr><td style="padding:30px 36px 20px">
        <p style="font-size:1.2rem;font-weight:700;color:#0E1B29;line-height:1.25;margin:0 0 10px;letter-spacing:-.01em">Verifica tu acceso</p>
        <p style="font-size:.88rem;color:#5A5465;line-height:1.8;margin:0;font-weight:400">Hola <strong style="color:#0E1B29;font-weight:600">{{ $userName }}</strong>, para completar tu inicio de sesión, por favor ingresa el siguiente código de verificación en el dashboard:</p>
      </td></tr>

      <!-- Caja del Código OTP -->
      <tr><td style="padding:0 36px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1EB;border-radius:8px;border-left:4px solid #EAB308">
          <tr><td style="padding:24px 20px; text-align: center;">
            <span style="font-size: 32px; letter-spacing: 8px; font-weight: bold; color: #0E1B29; font-family: monospace;">{{ $otp }}</span>
          </td></tr>
        </table>
      </td></tr>

      <!-- Aviso de Expiración -->
      <tr><td style="padding:0 36px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1EB;border-radius:8px">
          <tr><td style="padding:16px 20px">
            <p style="font-size:.82rem;color:#5A5465;line-height:1.75;margin:0;font-weight:400">Este código <strong style="color:#0E1B29;font-weight:600">expirará en 10 minutos</strong>. Si no intentaste iniciar sesión, puedes ignorar este correo de forma segura.</p>
          </td></tr>
        </table>
      </td></tr>

      <!-- Pie de página -->
      <tr><td style="background:#F5F1EB;padding:16px 36px;border-top:1px solid #EAE4DB">
        <p style="font-size:.7rem;color:#9B93A3;margin:0;text-align:center;font-weight:400">&copy; {{ date('Y') }} YEL GROUP, LLC. Todos los derechos reservados.</p>
      </td></tr>

    </table>
    </td></tr>
    </table>
  </div>
</div>
</body>
</html>