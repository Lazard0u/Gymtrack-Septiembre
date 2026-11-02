<?php
/**
 * Contrato automatizado pago_simulado_contracts. Verifica que el pago simulado funcione en desarrollo y que nunca pueda activarse en producción.
 * Finaliza con error si se rompe una garantía; no necesita base de datos ni red.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
foreach (['PagoProviderInterface', 'MercadoPagoProvider', 'MercadoPagoSimulado', 'PagoProviderFactory'] as $class) {
    require_once $root . '/backend/services/' . $class . '.php';
}

$failures = [];
function comprobar(bool $ok, string $message): void
{
    global $failures;
    if (!$ok) {
        $failures[] = $message;
    }
}
function proveedor(string $name, string $env): ?PagoProviderInterface
{
    putenv('PAYMENT_PROVIDER=' . $name);
    putenv('APP_ENV=' . $env);
    try {
        return PagoProviderFactory::configured();
    } catch (RuntimeException) {
        return null;
    }
}

comprobar(proveedor('mercado_pago_simulado', 'development') instanceof MercadoPagoSimulado, 'El proveedor simulado debe activarse en desarrollo.');
comprobar(proveedor('mercado_pago_simulado', 'demo') instanceof MercadoPagoSimulado, 'El proveedor simulado debe activarse en demo.');
comprobar(proveedor('mercado_pago_simulado', 'production') === null, 'El proveedor simulado nunca puede activarse en producción.');
comprobar(proveedor('mercado_pago', 'production') instanceof MercadoPagoProvider, 'El proveedor real debe seguir disponible en producción.');
comprobar(proveedor('otro_proveedor', 'development') === null, 'Un proveedor desconocido debe rechazarse.');

$checkout = (new MercadoPagoSimulado())->createCheckout(['referencia_externa' => 'GT-ABC123']);
comprobar($checkout['checkout_url'] === '/pago-simulado/GT-ABC123', 'El checkout simulado debe apuntar a una ruta interna de la app.');
comprobar((new MercadoPagoSimulado())->name() === 'mercado_pago', 'El pago simulado debe reutilizar el nombre del proveedor para no romper los estados.');

if ($failures) {
    fwrite(STDERR, "Contrato de pago simulado falló:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "OK pago_simulado_contracts\n";
