<?php
/**
 * Servicio PagoProviderInterface. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

interface PagoProviderInterface
{
    public function name(): string;
    public function isConfigured(): bool;
    public function createCheckout(array $payment): array;
    public function fetchPayment(string $providerPaymentId): array;
    public function refund(string $providerPaymentId, float $amount, string $idempotencyKey): array;
}
