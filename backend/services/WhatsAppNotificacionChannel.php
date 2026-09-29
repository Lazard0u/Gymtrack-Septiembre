<?php
/**
 * Servicio WhatsAppNotificacionChannel. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class WhatsAppNotificacionChannel implements NotificacionChannelInterface
{
    public function send(array $delivery, array $payload, NotificacionRepository $repository): ?string
    {
        if (!filter_var(getenv('FEATURE_WHATSAPP') ?: 'false', FILTER_VALIDATE_BOOL)) {
            throw new DomainException('whatsapp_feature_disabled');
        }
        throw new DomainException('whatsapp_adapter_unavailable');
    }
}
