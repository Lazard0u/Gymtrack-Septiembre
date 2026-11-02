<?php
/**
 * Servicio MercadoPagoSimulado. Reemplaza a Mercado Pago en desarrollo: el checkout vive dentro de la propia app y el pago se aprueba o rechaza a mano.
 * Nunca se usa en producción: PagoProviderFactory lo rechaza y scripts/production-preflight.sh bloquea el despliegue.
 */

declare(strict_types=1);

final class MercadoPagoSimulado implements PagoProviderInterface
{
    // Mismo nombre que el proveedor real para reutilizar columnas, estados y eventos de pagos.
    public function name(): string { return 'mercado_pago'; }
    public function isConfigured(): bool { return true; }

    public function createCheckout(array $payment): array
    {
        $url='/pago-simulado/'.rawurlencode((string)$payment['referencia_externa']);
        return ['preference_id'=>'SIM-'.$payment['referencia_externa'],'checkout_url'=>$url,'production_url'=>$url];
    }

    public function fetchPayment(string $providerPaymentId): array { throw new RuntimeException('El pago simulado no se consulta a ningún proveedor.'); }

    public function refund(string $providerPaymentId,float $amount,string $idempotencyKey): array { return ['id'=>'SIM-'.substr($idempotencyKey,0,8),'status'=>'approved']; }
}
