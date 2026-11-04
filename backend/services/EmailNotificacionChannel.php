<?php
/**
 * Canal de correo del despachador de notificaciones. Valida el destinatario, limpia los saltos de línea del
 * asunto y agrega el enlace para darse de baja en los mensajes de marketing.
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
