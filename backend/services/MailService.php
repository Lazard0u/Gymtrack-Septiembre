<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Adaptador único para el envío de correos de GymTrack.
 *
 * En desarrollo guarda los mensajes en un buzón local privado que puede leer
 * la CLI. En producción PHPMailer construye el mensaje y lo entrega al agente
 * de correo configurado por PHP/mail (msmtp dentro de la imagen oficial).
 */
final class MailService
{
    /**
     * Envía texto plano y, opcionalmente, HTML como multipart/alternative.
     *
     * @throws RuntimeException cuando la configuración o el proveedor fallan.
     */
    public function send(string $email, string $subject, string $textBody, ?string $htmlBody = null): void
    {
        $this->validateMessage($email, $subject);
        $transport = strtolower(trim((string) (getenv('MAIL_TRANSPORT') ?: 'log')));
        $environment = strtolower((string) (getenv('APP_ENV') ?: 'production'));

        if ($transport === 'log') {
            if ($environment === 'production') {
                throw new RuntimeException('MAIL_TRANSPORT=log no está permitido en producción.');
            }
            $this->storeInLocalMailbox($email, $subject, $textBody, $htmlBody);
            return;
        }

        if (!in_array($transport, ['mail', 'smtp'], true)) {
            throw new RuntimeException('Transporte de correo no soportado.');
        }

        $from = trim((string) getenv('MAIL_FROM'));
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('MAIL_FROM es obligatorio y debe ser un correo válido.');
        }
        $fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'GymTrack'));
        if (preg_match('/[\r\n]/', $fromName)) {
            throw new RuntimeException('MAIL_FROM_NAME contiene caracteres no permitidos.');
        }

        $this->sendWithPhpMailer($email, $subject, $textBody, $htmlBody, $from, $fromName, $transport);
    }

    /** Impide inyección de cabeceras y destinatarios inválidos. */
    private function validateMessage(string $email, string $subject): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
            throw new RuntimeException('El destinatario del correo no es válido.');
        }
        if ($subject === '' || mb_strlen($subject) > 160 || preg_match('/[\r\n]/', $subject)) {
            throw new RuntimeException('El asunto del correo no es válido.');
        }
    }

    /**
     * El buzón local simula entrega sin usar Internet. No es un log operativo:
     * sólo existe fuera de producción, usa permisos 0600 y se ignora en Git.
     */
    private function storeInLocalMailbox(
        string $email,
        string $subject,
        string $textBody,
        ?string $htmlBody
    ): void {
        $path = (string) (getenv('MAIL_LOG_PATH') ?: '/tmp/gymtrack-mail/mail.log');
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar el buzón local.');
        }

        $record = json_encode([
            'fecha' => date(DATE_ATOM),
            'para' => $email,
            'asunto' => $subject,
            // "mensaje" se conserva para compatibilidad con la CLI y pruebas existentes.
            'mensaje' => $textBody,
            'html' => $htmlBody,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

        if (file_put_contents($path, $record, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir en el buzón local.');
        }
        @chmod($path, 0600);
    }

    /**
     * PHPMailer se ocupa de MIME, codificación UTF-8 y cabeceras seguras.
     * isMail() conserva la infraestructura existente: en producción PHP pasa
     * el mensaje a msmtp, que mantiene las credenciales fuera del repositorio.
     */
    private function sendWithPhpMailer(
        string $email,
        string $subject,
        string $textBody,
        ?string $htmlBody,
        string $from,
        string $fromName,
        string $transport
    ): void {
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer no está instalado. Ejecutá composer install.');
        }

        try {
            $mailer = new PHPMailer(true);
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->Encoding = PHPMailer::ENCODING_BASE64;
            $mailer->XMailer = 'GymTrack';

            if ($transport === 'smtp') {
                $this->configureSmtp($mailer);
            } else {
                $mailer->isMail();
            }

            $mailer->setFrom($from, $fromName);
            $mailer->addAddress($email);
            $mailer->Subject = $subject;

            if ($htmlBody !== null && trim($htmlBody) !== '') {
                $mailer->isHTML(true);
                $mailer->Body = $htmlBody;
                $mailer->AltBody = $textBody;
            } else {
                $mailer->isHTML(false);
                $mailer->Body = $textBody;
            }

            $mailer->send();
        } catch (Throwable $error) {
            // La causa técnica queda encadenada para diagnóstico interno, pero
            // el controlador muestra al usuario un mensaje genérico y seguro.
            throw new RuntimeException('El proveedor de correo rechazó el envío.', 0, $error);
        }
    }

    /**
     * Configura SMTP autenticado sin escribir credenciales en código o logs.
     * Gmail utiliza STARTTLS en el puerto 587 o TLS implícito en el 465.
     */
    private function configureSmtp(PHPMailer $mailer): void
    {
        $host = trim((string) getenv('SMTP_HOST'));
        $port = (int) (getenv('SMTP_PORT') ?: 587);
        $username = trim((string) getenv('SMTP_USERNAME'));
        $password = (string) getenv('SMTP_PASSWORD');
        $encryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')));

        if ($host === '' || preg_match('/[\s\x00-\x1F\x7F]/', $host)) {
            throw new RuntimeException('SMTP_HOST no está configurado correctamente.');
        }
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('SMTP_PORT no es válido.');
        }
        if ($username === '' || $password === '') {
            throw new RuntimeException('Faltan las credenciales SMTP.');
        }
        if (!in_array($encryption, ['tls', 'ssl'], true)) {
            throw new RuntimeException('SMTP_ENCRYPTION debe ser tls o ssl.');
        }

        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = $port;
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->SMTPSecure = $encryption === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Timeout = 15;
        $mailer->SMTPDebug = 0;
    }
}
