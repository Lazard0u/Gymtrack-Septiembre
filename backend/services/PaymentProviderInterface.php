<?php

declare(strict_types=1);

interface PaymentProviderInterface
{
    public function name(): string;
    public function isConfigured(): bool;
    public function createCheckout(array $payment): array;
    public function fetchPayment(string $providerPaymentId): array;
    public function refund(string $providerPaymentId, float $amount, string $idempotencyKey): array;
}
