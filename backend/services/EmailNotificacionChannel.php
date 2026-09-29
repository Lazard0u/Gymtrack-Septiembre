<?php
/**
 * Servicio EmailNotificacionChannel. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class EmailNotificacionChannel implements NotificacionChannelInterface
{
    public function send(array $delivery, array $payload, NotificacionRepository $repository): ?string
    {
        $email = trim((string) ($delivery['recipient_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('recipient_email_invalid');
        $subject = preg_replace('/[\r\n]+/u', ' ', trim((string) ($payload['title'] ?? 'GymTrack'))) ?: 'GymTrack';
        $body = (string) ($payload['body'] ?? '');
        if (($delivery['categoria'] ?? '') === 'marketing' && !empty($payload['unsubscribe_url'])) {
            $body .= "\n\nDejar de recibir publicidad: " . $payload['unsubscribe_url'];
        }
        (new MailService())->send($email, mb_substr($subject, 0, 160), $body);
        return null;
    }
}
