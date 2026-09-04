<?php

declare(strict_types=1);

/**
 * Construye correos transaccionales con la identidad visual de GymTrack.
 *
 * Devuelve siempre una versión de texto y otra HTML. MailService decide cómo
 * transportarlas; la plantilla nunca conoce credenciales ni habla con la red.
 */
final class EmailTemplate
{
    /**
     * @return array{subject:string,text:string,html:string}
     */
    public static function emailVerification(
        string $firstName,
        string $verificationUrl,
        int $expiresInSeconds
    ): array {
        $safeName = htmlspecialchars(trim($firstName) ?: 'hola', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $brandUrl = self::frontendUrl() . '/favicon.ico';
        $safeBrandUrl = htmlspecialchars($brandUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $hours = max(1, (int) ceil($expiresInSeconds / 3600));
        $expiryCopy = $hours === 1 ? '1 hora' : "{$hours} horas";
        $subject = 'Verificá tu correo de GymTrack';

        // La versión plana también sirve como alternativa accesible y fallback.
        $text = "Hola {$firstName},\n\n"
            . "Confirmá que este correo te pertenece para activar tu cuenta de GymTrack.\n\n"
            . "Verificar correo: {$verificationUrl}\n\n"
            . "El enlace vence en {$expiryCopy} y puede utilizarse una sola vez.\n\n"
            . "Si no creaste una cuenta en GymTrack, podés ignorar este mensaje.\n\n"
            . "Equipo GymTrack";

        // Tablas y estilos inline ofrecen el soporte más estable entre clientes de correo.
        $html = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$subject}</title>
  <style>
    @media only screen and (max-width: 620px) {
      .email-shell { width: 100% !important; }
      .email-body { padding: 28px 22px !important; }
      .email-button { display: block !important; text-align: center !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#0b0e12;color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Confirmá tu correo para activar tu cuenta de GymTrack.</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#0b0e12;">
    <tr>
      <td align="center" style="padding:32px 12px;">
        <table role="presentation" class="email-shell" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;max-width:600px;border:1px solid #273140;border-radius:16px;background:#11161d;overflow:hidden;">
          <tr>
            <td style="padding:24px 28px;border-bottom:1px solid #273140;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td style="padding-right:12px;"><img src="{$safeBrandUrl}" width="40" height="40" alt="Logo de GymTrack" style="display:block;width:40px;height:40px;border:0;border-radius:9px;"></td>
                  <td style="font-size:20px;line-height:24px;font-weight:700;color:#f4f7fb;">Gym<span style="color:#51a8ff;">Track</span></td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td class="email-body" style="padding:38px 40px 40px;">
              <p style="margin:0 0 14px;color:#aeb9c8;font-size:15px;line-height:23px;">Hola, {$safeName}.</p>
              <h1 style="margin:0 0 18px;color:#f4f7fb;font-size:30px;line-height:37px;letter-spacing:-0.6px;">Verificá tu correo</h1>
              <p style="margin:0 0 26px;color:#c8d1dc;font-size:16px;line-height:25px;">Confirmá que esta dirección te pertenece para activar tu cuenta y acceder de forma segura a GymTrack.</p>
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                  <td>
                    <a class="email-button" href="{$safeUrl}" target="_blank" style="display:inline-block;min-height:20px;padding:14px 22px;border-radius:10px;background:#1479e8;color:#ffffff;font-size:16px;line-height:20px;font-weight:700;text-decoration:none;">Verificar mi correo</a>
                  </td>
                </tr>
              </table>
              <p style="margin:26px 0 8px;color:#aeb9c8;font-size:13px;line-height:20px;">Si el botón no funciona, copiá y pegá este enlace:</p>
              <p style="margin:0 0 24px;font-size:13px;line-height:20px;word-break:break-all;"><a href="{$safeUrl}" style="color:#75baff;text-decoration:underline;">{$safeUrl}</a></p>
              <div style="padding:16px;border-radius:10px;background:#18212c;color:#c8d1dc;font-size:13px;line-height:20px;">
                El enlace vence en <strong style="color:#f4f7fb;">{$expiryCopy}</strong> y sólo puede utilizarse una vez.
              </div>
              <p style="margin:24px 0 0;color:#8996a8;font-size:13px;line-height:20px;">Si no creaste una cuenta en GymTrack, ignorá este mensaje. No se realizará ningún cambio.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 28px;border-top:1px solid #273140;color:#718095;font-size:12px;line-height:18px;">Mensaje automático de seguridad de GymTrack.</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    /**
     * El logo y los enlaces se basan en la URL configurada, nunca en un dominio inventado.
     */
    public static function frontendUrl(): string
    {
        $url = rtrim(trim((string) getenv('FRONTEND_URL')), '/');
        $environment = strtolower((string) (getenv('APP_ENV') ?: 'production'));
        if ($url === '' && $environment !== 'production') {
            $url = 'http://localhost:5173';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('FRONTEND_URL debe ser una URL HTTP o HTTPS válida.');
        }

        return $url;
    }
}
