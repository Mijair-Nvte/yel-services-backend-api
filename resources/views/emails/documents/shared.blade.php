<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurso Compartido</title>
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
            <span style="font-size:1.2rem;font-weight:700;color:#FFFFFF;letter-spacing:.02em;">YEL Pro</span>
          </td>
          <td align="right">
            <!-- Cambié el texto y el emoji a algo más enfocado en educación/recursos -->
            <span style="font-size:.68rem;font-weight:600;color:#4F46E5;letter-spacing:.06em;text-transform:uppercase">📚 Recurso Informativo</span>
          </td>
        </tr></table>
      </td></tr>

      <tr><td style="padding:30px 36px 20px">
        <!-- Título más amigable -->
        <p style="font-size:1.2rem;font-weight:700;color:#0E1B29;line-height:1.25;margin:0 0 10px;letter-spacing:-.01em">Te han compartido un nuevo recurso</p>
        
        <!-- Texto enfocado en aportar valor, quitando la palabra "revisión" -->
        <p style="font-size:.88rem;color:#5A5465;line-height:1.8;margin:0;font-weight:400">Hola, <strong style="color:#0E1B29;font-weight:600">{{ $senderName }}</strong> ha compartido este material contigo. Esperamos que esta información te sea de gran utilidad.</p>
      </td></tr>

      @if($customMessage)
      <tr><td style="padding:0 36px 20px">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1EB;border-radius:8px;border-left:4px solid #4F46E5">
          <tr><td style="padding:16px 20px">
            <p style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B93A3;margin:0 0 8px">Mensaje de {{ $senderName }}</p>
            <p style="font-size:.85rem;color:#0E1B29;margin:0;font-style:italic;">"{{ $customMessage }}"</p>
          </td></tr>
        </table>
      </td></tr>
      @endif

      <tr><td style="padding:0 36px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1EB;border-radius:8px">
          <tr><td style="padding:16px 20px">
            <table width="100%"><tr>
              <td style="font-size:.8rem;color:#9B93A3;width:30%;font-weight:400">Recurso</td>
              <td style="font-size:.8rem;color:#0E1B29;font-weight:600">{{ $documentTitle }}</td>
            </tr></table>
          </td></tr>
        </table>
      </td></tr>

      <tr><td style="padding:0 36px 32px;text-align:center">
        <!-- Cambié el botón a "Ver Recurso" -->
        <a href="{{ $documentUrl }}" target="_blank" style="display:inline-block;background:#4F46E5;color:#FFFFFF;font-family:'Work Sans',Arial,sans-serif;font-size:.88rem;font-weight:600;padding:13px 28px;border-radius:8px;text-decoration:none">Ver Recurso →</a>
      </td></tr>

     

    </table>
    </td></tr>
    </table>
  </div>
</div>
</body>
</html>