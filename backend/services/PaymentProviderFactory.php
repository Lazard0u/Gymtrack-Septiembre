<?php

declare(strict_types=1);

final class PaymentProviderFactory
{
    public static function configured(): PaymentProviderInterface
    {
        $name=strtolower(trim((string)(getenv('PAYMENT_PROVIDER')?:'mercado_pago')));
        if($name!=='mercado_pago')throw new RuntimeException('El proveedor de pagos configurado no está soportado.');
        return new MercadoPagoProvider();
    }
}
