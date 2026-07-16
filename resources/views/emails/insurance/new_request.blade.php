<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Prospecto de Seguro</title>
</head>
<body style="margin: 0; padding: 0; background-color: #EDEAE3;">

<div class="em-wrap">
  <div class="em-frame">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#EDEAE3;font-family:'Work Sans',Arial,sans-serif">
    <tr><td align="center" style="padding:36px 16px">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:10px;overflow:hidden">

  <tr><td style="background:#0E1B29;padding:22px 36px">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td>
<img
    src="https://api.yel.services/assets/img/logo-yel-investor-2.png"
    alt="Yel Investor"
    width="180"
    style="display:block;">      </td>
      <td align="right"><span style="font-size:.68rem;font-weight:600;color:rgba(255,255,255,.45);letter-spacing:.06em;text-transform:uppercase">🔔 Nuevo referido</span></td>
    </tr></table>
  </td></tr>

      <tr><td style="padding:30px 36px 20px">
        <p style="font-size:1.2rem;font-weight:700;color:#0E1B29;line-height:1.25;margin:0 0 10px;letter-spacing:-.01em">Tienes una nueva solicitud.<br>Un cliente necesita cobertura.</p>
        <p style="font-size:.88rem;color:#5A5465;line-height:1.8;margin:0;font-weight:400">Hola <strong style="color:#0E1B29;font-weight:600">{{ $user->name }}</strong>, se ha registrado exitosamente una nueva aplicación de seguro en tu panel.</p>
      </td></tr>

      <tr><td style="padding:0 36px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1EB;border-radius:8px;border-left:4px solid #E83983">
          <tr><td style="padding:18px 20px">
            <p style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B93A3;margin:0 0 12px">Datos del solicitante</p>
            <table width="100%">
              <tr><td style="padding:6px 0;border-bottom:1px solid #EAE4DB">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Nombre</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500">{{ $application->applicant_name }}</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #EAE4DB">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Correo</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500">{{ $application->applicant_email }}</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #EAE4DB">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Teléfono</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500">{{ $application->applicant_phone }}</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #EAE4DB">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Tipo de Seguro</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500;text-transform:capitalize;">{{ $application->insurance_type ?? 'General' }}</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:6px 0">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Fecha</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500">{{ $application->created_at->format('d M, Y') }}</td>
                </tr></table>
              </td></tr>
            </table>
          </td></tr>
        </table>
      </td></tr>

      <tr><td style="padding:0 36px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1EB;border-radius:8px">
          <tr><td style="padding:16px 20px">
            <p style="font-size:.78rem;font-weight:600;color:#0E1B29;margin:0 0 6px">¿Qué sigue?</p>
            <p style="font-size:.82rem;color:#5A5465;line-height:1.75;margin:0;font-weight:400">El equipo administrativo de Yel Insurance revisará la información proporcionada. <strong style="color:#0E1B29;font-weight:600">Te notificaremos cuando el estatus cambie a revisión o aprobado.</strong></p>
          </td></tr>
        </table>
      </td></tr>

      <tr><td style="padding:0 36px 32px;text-align:center">
        <a href="https://www.yel.services/dashboard/wsk_01KFYBFC1P9ENY7CBWD2PBTE8A/insurece" style="display:inline-block;background:#4F46E5;color:#FFFFFF;font-family:'Work Sans',Arial,sans-serif;font-size:.88rem;font-weight:600;padding:13px 28px;border-radius:8px;text-decoration:none">Ver en Dashboard →</a>
      </td></tr>

      <tr><td style="background:#F5F1EB;padding:16px 36px;border-top:1px solid #EAE4DB">
        <p style="font-size:.7rem;color:#9B93A3;margin:0;text-align:center;font-weight:400">YEL GROUP, LLC. Soporte para Partners · support@yelinvestor.com</p>
      </td></tr>

    </table>
    </td></tr>
    </table>
  </div>
</div>
</body>
</html>