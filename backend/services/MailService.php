<?php

declare(strict_types=1);

final class MailService
{
    public function send(string $email, string $subject, string $body): void
    {
        $transport = (string) (getenv('MAIL_TRANSPORT') ?: 'log');
        $environment = (string) (getenv('APP_ENV') ?: 'production');
        if ($transport === 'log') {
            if ($environment === 'production') {
                throw new RuntimeException('MAIL_TRANSPORT=log no está permitido en producción.');
            }
            $path = (string) (getenv('MAIL_LOG_PATH') ?: '/tmp/gymtrack-mail/mail.log');
            $directory = dirname($path);
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('No se pudo preparar el buzón local.');
            }
            $line = json_encode([
                'fecha' => date(DATE_ATOM),
                'para' => $email,
                'asunto' => $subject,
                'mensaje' => $body,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
            @chmod($path, 0600);
            return;
        }
        if ($transport !== 'mail') {
            throw new RuntimeException('Transporte de correo no soportado.');
        }
        $from = trim((string) getenv('MAIL_FROM'));
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('MAIL_FROM es obligatorio.');
        }
        $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        if (!mail($email, $subject, $body, $headers)) {
            throw new RuntimeException('El proveedor de correo rechazó el envío.');
        }
    }
}
