<?php
/**
 * Servicio PagoProviderFactory. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
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
