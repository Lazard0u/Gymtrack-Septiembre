<?php
/**
 * Canal de WhatsApp, todavía sin proveedor: lanza whatsapp_feature_disabled o whatsapp_adapter_unavailable.
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
