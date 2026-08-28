<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de Estatus</title>
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
            <img src="{{ url('assets/img/Yel Pro_logo_cream.png') }}" alt="YEL PRO" width="120" style="display:block; max-width: 120px; height: auto;">
          </td>
          <td align="right">
            <span style="font-size:.68rem;font-weight:600;color:#4F46E5;letter-spacing:.06em;text-transform:uppercase">🔄 Estatus Actualizado</span>
          </td>
        </tr></table>
      </td></tr>

      <tr><td style="padding:30px 36px 20px">
        <p style="font-size:1.2rem;font-weight:700;color:#0E1B29;line-height:1.25;margin:0 0 10px;letter-spacing:-.01em">Actualización en tu prospecto.</p>
        <p style="font-size:.88rem;color:#5A5465;line-height:1.8;margin:0;font-weight:400">Hola <strong style="color:#0E1B29;font-weight:600">{{ $partner->name }}</strong>, el equipo administrativo ha revisado tu prospecto y ha actualizado su estatus.</p>
      </td></tr>

      <tr><td style="padding:0 36px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1EB;border-radius:8px;border-left:4px solid #4F46E5">
          <tr><td style="padding:18px 20px">
            
            <table width="100%">
              <tr><td style="padding:6px 0;border-bottom:1px solid #EAE4DB">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Prospecto</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500">{{ $application->applicant_name }}</td>
                </tr></table>
              </td></tr>
              
              <tr><td style="padding:12px 0 6px 0;">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Nuevo Estatus</td>
                  <td style="font-size:.9rem;color:#0E1B29;font-weight:700;text-transform:uppercase;">
                      {{-- Puedes poner colores según el estatus --}}
                      @if($application->status == 'approved' || $application->status == 'completed')
                          <span style="color: #10B981;">{{ $application->status }} 🎉</span>
                      @elseif($application->status == 'rejected')
                          <span style="color: #EF4444;">{{ $application->status }}</span>
                      @else
                          <span style="color: #F59E0B;">{{ $application->status }} ⏳</span>
                      @endif
                  </td>
                </tr></table>
              </td></tr>
            </table>

          </td></tr>
        </table>
      </td></tr>

    

      <tr><td style="background:#F5F1EB;padding:16px 36px;border-top:1px solid #EAE4DB">
        <p style="font-size:.7rem;color:#9B93A3;margin:0;text-align:center;font-weight:400">YEL SERVICES · Notificaciones · soporte@yaestoylisto.com</p>
      </td></tr>

    </table>
    </td></tr>
    </table>
  </div>
</div>
</body>
</html>