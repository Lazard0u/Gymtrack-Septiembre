<?php
/**
 * Servicio NotificationChannelInterface. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

interface NotificationChannelInterface
{
    public function send(array $delivery, array $payload, NotificationRepository $repository): ?string;
}
