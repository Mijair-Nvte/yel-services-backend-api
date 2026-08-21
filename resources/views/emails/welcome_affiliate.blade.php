<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido</title>
</head>
<body style="margin: 0; padding: 0; background-color: #EDEAE3;">

<div class="em-wrap">
  <div class="em-frame">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#EDEAE3;font-family:'Work Sans',Arial,sans-serif">
    <tr><td align="center" style="padding:36px 16px">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:10px;overflow:hidden">

      <!-- Encabezado -->
     <tr><td style="background:#0E1B29;padding:22px 36px">
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
          <td>
            <img src="{{ url('assets/img/YEL_Group_White.png') }}" alt="YEL GROUP" width="120" style="display:block; max-width: 120px; height: auto;">
          </td>
          <td align="right">
            <span style="font-size:.68rem;font-weight:600;color:#EAB308;letter-spacing:.06em;text-transform:uppercase">✨ Nueva Cuenta</span>
          </td>
        </tr></table>
      </td></tr>

      <!-- Mensaje Principal -->
      <tr><td style="padding:30px 36px 20px">
        <p style="font-size:1.2rem;font-weight:700;color:#0E1B29;line-height:1.25;margin:0 0 10px;letter-spacing:-.01em">¡Bienvenido a YEL PRO!</p>
        <p style="font-size:.88rem;color:#5A5465;line-height:1.8;margin:0;font-weight:400">Hola, <strong style="color:#0E1B29;font-weight:600">{{ $user->name }}</strong>. Tu cuenta ha sido creada exitosamente y ya puedes acceder a todas las herramientas del sistema.</p>
      </td></tr>

      <!-- Caja Resaltada para el mensaje de soporte -->
      <tr><td style="padding:0 36px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1EB;border-radius:8px;border-left:4px solid #EAB308">
          <tr><td style="padding:16px 20px">
            <p style="font-size:.85rem;color:#0E1B29;margin:0;font-weight:500">¿Tienes alguna duda?</p>
            <p style="font-size:.85rem;color:#5A5465;margin:4px 0 0 0;">No dudes en contactar a soporte, estamos aquí para ayudarte en cualquier momento.</p>
          </td></tr>
        </table>
      </td></tr>

    

      <!-- Pie de página (Firmas) -->
      <tr><td style="background:#F5F1EB;padding:16px 36px;border-top:1px solid #EAE4DB">
        <p style="font-size:.7rem;color:#9B93A3;margin:0 0 4px;text-align:center;font-weight:400">
          Saludos cordiales,
        </p>
        <p style="font-size:.7rem;color:#0E1B29;margin:0;text-align:center;font-weight:600">
          El equipo de seguridad de YEL SERVICES.
        </p>
      </td></tr>

    </table>
    </td></tr>
    </table>
  </div>
</div>
</body>
</html>