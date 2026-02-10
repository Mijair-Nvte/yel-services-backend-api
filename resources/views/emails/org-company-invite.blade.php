<!DOCTYPE html>

<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Invitación</title>
</head>

<body style="margin:0;padding:0;background-color:#f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5;padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color:#ffffff;font-family:Arial, Helvetica, sans-serif;color:#000000;padding:40px 30px;">

                    ```
                    <!-- Header -->
                    <tr>
                        <td>
                            <h2
                                style="margin:0 0 20px 0;font-size:22px;font-weight:600;border-bottom:1px solid #000;padding-bottom:10px;">
                                Invitación a {{ $company->name }}
                            </h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="font-size:15px;line-height:1.6;padding:10px 0 25px 0;">
                            Has sido invitado a formar parte de <strong>{{ $company->name }}</strong>.
                            Para continuar y aceptar esta invitación, haz clic en el botón de abajo.
                        </td>
                    </tr>

                    <!-- Button -->
                    <tr>
                        <td align="center" style="padding:30px 0;">
                            <a href="{{ $url }}"
                                style="display:inline-block;padding:14px 30px;border:2px solid #000;color:#000;text-decoration:none;font-size:14px;font-weight:600;letter-spacing:0.5px;">
                                ACEPTAR INVITACIÓN
                            </a>
                        </td>
                    </tr>

                    <!-- Expiration -->
                    <tr>
                        <td style="font-size:14px;line-height:1.6;color:#333;padding-bottom:30px;">
                            Por motivos de seguridad, este enlace es válido únicamente durante
                            <strong>7 días</strong>.
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="font-size:13px;color:#666;border-top:1px solid #e0e0e0;padding-top:20px;line-height:1.5;">
                            Si no esperabas esta invitación, puedes ignorar este correo con total tranquilidad.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
        ```

    </table>
</body>

</html>
