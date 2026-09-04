<?php
/**
 * Contrato automatizado security_concurrency_contracts. Inspecciona archivos o comportamiento y finaliza con error si se rompe una garantía del proyecto.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

require_once $root . '/backend/services/Security.php';

$rateLimiter = file_get_contents($root . '/backend/services/RateLimiter.php');
$security = file_get_contents($root . '/backend/services/Security.php');
if (!str_contains($security, "getenv('TRUST_PROXY_HEADERS')")
    || !str_contains($security, "\$_SERVER['HTTP_X_REAL_IP']")
    || !str_contains($security, 'FILTER_VALIDATE_IP')) {
    $failures[] = 'Security::ip() no valida la IP real del proxy privado.';
}
putenv('TRUST_PROXY_HEADERS=true');
$_SERVER['REMOTE_ADDR'] = '172.20.0.4';
$_SERVER['HTTP_X_REAL_IP'] = '203.0.113.27';
if (Security::ip() !== '203.0.113.27') {
    $failures[] = 'Security::ip() no conserva la IP validada que entrega Nginx.';
}
$_SERVER['HTTP_X_REAL_IP'] = 'valor-invalido';
if (Security::ip() !== '172.20.0.4') {
    $failures[] = 'Security::ip() no vuelve a REMOTE_ADDR ante un header inválido.';
}
putenv('TRUST_PROXY_HEADERS=false');
$_SERVER['HTTP_X_REAL_IP'] = '198.51.100.9';
if (Security::ip() !== '172.20.0.4') {
    $failures[] = 'Security::ip() confía en headers sin habilitación explícita.';
}
if (!preg_match('/public function fail\(.*?\n    }/s', $rateLimiter, $failMethod)) {
    $failures[] = 'No se pudo inspeccionar RateLimiter::fail().';
} else {
    $source = $failMethod[0];
    if (!str_contains($source, 'intentos = intentos + 1')
        || !str_contains($source, 'ON DUPLICATE KEY UPDATE')) {
        $failures[] = 'El rate limiter no incrementa intentos de forma atómica.';
    }
    if (str_contains($source, '$this->find(')) {
        $failures[] = 'RateLimiter::fail() conserva el patrón read-then-write vulnerable a incrementos perdidos.';
    }
}

$payments = file_get_contents($root . '/backend/models/PaymentRepository.php');
if (!preg_match('/public function refund\(.*?\n    }\n\n    public function reportRows/s', $payments, $refundMethod)) {
    $failures[] = 'No se pudo inspeccionar PaymentRepository::refund().';
} else {
    $source = $refundMethod[0];
    $reservationPosition = strpos($source, 'VALUES (?, ?,"pendiente",?,?,?)');
    $providerPosition = strpos($source, '$provider->refund(');
    if ($reservationPosition === false || $providerPosition === false || $reservationPosition > $providerPosition) {
        $failures[] = 'El reembolso no reserva una fila pendiente antes del efecto externo.';
    }
    foreach (['$this->payment($gymId, $paymentId, true)', 'refund_already_requested', 'estado IN ("pendiente","aprobado")', 'WHERE id=? AND estado="pendiente"', '$providerStatus !== \'approved\''] as $contract) {
        if (!str_contains($source, $contract)) {
            $failures[] = "El reembolso concurrente omite {$contract}.";
        }
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contratos de concurrencia correctos.\n";
