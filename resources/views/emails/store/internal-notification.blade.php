<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Venta Registrada</title>
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
            <span style="font-size:.68rem;font-weight:600;color:rgba(255,255,255,.45);letter-spacing:.06em;text-transform:uppercase">YEL PRO</span>
          </td>
          <td align="right">
            <span style="font-size:.68rem;font-weight:600;color:rgba(255,255,255,.45);letter-spacing:.06em;text-transform:uppercase">🔔 Venta Tienda</span>
          </td>
        </tr></table>
      </td></tr>

      <tr><td style="padding:30px 36px 20px">
        <p style="font-size:1.2rem;font-weight:700;color:#0E1B29;line-height:1.25;margin:0 0 10px;letter-spacing:-.01em">Nueva transacción registrada correctamente</p>
        
        <p style="font-size:.88rem;color:#5A5465;line-height:1.8;margin:0;font-weight:400">
          Hola <strong style="color:#0E1B29;font-weight:600">{{ $recipientName }}</strong>, se te notifica en tu rol de <strong style="color:#4F46E5;font-weight:600">{{ $roleType }}</strong> lo siguiente:
          <br>
          @if($roleType === 'Afiliado')
            ¡Felicidades! Uno de tus referidos ha completado una compra y se ha registrado tu comisión en el panel.
          @else
            Se ha completado un nuevo cobro transaccional a través del flujo de la tienda en línea.
          @endif
        </p>
      </td></tr>

      <tr><td style="padding:0 36px 24px">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1EB;border-radius:8px;border-left:4px solid #4F46E5">
          <tr><td style="padding:18px 20px">
            <p style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B93A3;margin:0 0 12px">Estructura Financiera de la Venta</p>
            <table width="100%">
              <tr><td style="padding:6px 0;border-bottom:1px solid #EAE4DB">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Concepto</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500">{{ $sale->product_name }}</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #EAE4DB">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Monto del Servicio</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500">${{ number_format($sale->total_amount, 2) }} USD</td>
                </tr></table>
              </td></tr>
              
              @if($sale->seller_id && ($roleType === 'Afiliado' || $roleType === 'Administrador'))
              <tr><td style="padding:6px 0;border-bottom:1px solid #EAE4DB">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Comisión Partner</td>
                  <td style="font-size:.8rem;color:#16A34A;font-weight:600">${{ number_format($sale->commission_amount, 2) }} USD</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #EAE4DB">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Código Utilizado</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500;text-transform:uppercase;">{{ $sale->referral_code }}</td>
                </tr></table>
              </td></tr>
              @endif

              <tr><td style="padding:6px 0">
                <table width="100%"><tr>
                  <td style="font-size:.8rem;color:#9B93A3;width:40%;font-weight:400">Identificador Interno</td>
                  <td style="font-size:.8rem;color:#0E1B29;font-weight:500;font-family:monospace;">{{ $sale->uid }}</td>
                </tr></table>
              </td></tr>
            </table>
          </td></tr>
        </table>
      </td></tr>

      <tr><td style="padding:0 36px 32px;text-align:center">
        @if($roleType === 'Afiliado')
          <a href="{{ config('app.frontend_url') }}/dashboard/partner/sales" style="display:inline-block;background:#4F46E5;color:#FFFFFF;font-family:'Work Sans',Arial,sans-serif;font-size:.88rem;font-weight:600;padding:13px 28px;border-radius:8px;text-decoration:none">Revisar mis comisiones →</a>
        @else
          <a href="{{ config('app.frontend_url') }}/dashboard/admin/sales" style="display:inline-block;background:#0E1B29;color:#FFFFFF;font-family:'Work Sans',Arial,sans-serif;font-size:.88rem;font-weight:600;padding:13px 28px;border-radius:8px;text-decoration:none">Ver Gestión de Ventas →</a>
        @endif
      </td></tr>

      <tr><td style="background:#F5F1EB;padding:16px 36px;border-top:1px solid #EAE4DB">
        <p style="font-size:.7rem;color:#9B93A3;margin:0;text-align:center;font-weight:400">YEL PRO · Notificaciones de Tienda · soporte@yaestoylisto.com</p>
      </td></tr>

    </table>
    </td></tr>
    </table>
  </div>
</div>
</body>
</html>