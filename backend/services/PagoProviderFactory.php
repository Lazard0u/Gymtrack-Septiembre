<?php
/**
 * Elige el proveedor de pagos según PAYMENT_PROVIDER. Rechaza el simulado cuando APP_ENV=production.
 */

declare(strict_types=1);

final class PagoProviderFactory
{
    public static function configured(): PagoProviderInterface
    {
        $name=strtolower(trim((string)(getenv('PAYMENT_PROVIDER')?:'mercado_pago')));
        if($name==='mercado_pago_simulado'){
            if(strtolower(trim((string)(getenv('APP_ENV')?:'')))==='production')throw new RuntimeException('El pago simulado no puede usarse en producción.');
            return new MercadoPagoSimulado();
        }
        if($name!=='mercado_pago')throw new RuntimeException('El proveedor de pagos configurado no está soportado.');
        return new MercadoPagoProvider();
    }
}
