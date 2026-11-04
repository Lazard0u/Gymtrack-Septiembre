<?php
/**
 * Contrato de un canal de notificación: send() entrega un mensaje y devuelve un identificador externo o null.
 */

declare(strict_types=1);

interface NotificacionChannelInterface
{
    public function send(array $delivery, array $payload, NotificacionRepository $repository): ?string;
}
