<?php
/**
 * Canal interno: guarda la notificación en la bandeja del usuario (la campana de la aplicación).
 */

declare(strict_types=1);

final class InternalNotificacionChannel implements NotificacionChannelInterface
{
    public function send(array $delivery, array $payload, NotificacionRepository $repository): ?string
    {
        $id = $repository->createInternal($delivery, $payload);
        return 'internal:' . $id;
    }
}
