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
        if($name!=='mercado_pago')throw new RuntimeException('El proveedor de pagos configurado no está soportado.');
        return new MercadoPagoProvider();
    }
}
