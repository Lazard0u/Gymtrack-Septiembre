<?php
/**
 * Contrato de un proveedor de pagos: configuración, creación del checkout, consulta del estado de un pago y
 * reembolso. Lo implementan MercadoPagoProvider y MercadoPagoSimulado.
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
