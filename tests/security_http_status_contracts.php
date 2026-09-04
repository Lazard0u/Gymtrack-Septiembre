<?php
/**
 * Contrato automatizado security_http_status_contracts. Inspecciona archivos o comportamiento y finaliza con error si se rompe una garantía del proyecto.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$responder = file_get_contents($root . '/backend/services/ApiResponder.php');
$sessions = file_get_contents($root . '/backend/services/SessionManager.php');

if (!str_contains($responder, "if (\$status === 419)")) {
    $failures[] = 'ApiResponder no trata 419 de forma explícita.';
}
if (!str_contains($responder, "header('HTTP/1.1 419 Page Expired')")) {
    $failures[] = 'ApiResponder no emite una línea HTTP válida para 419 bajo Apache.';
}
if (!str_contains($sessions, "ApiResponder::error(\$status, \$status === 419 ? 'csrf_expired'")) {
    $failures[] = 'Las rutas administrativas no conservan el código csrf_expired.';
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contrato HTTP 419 correcto.\n";
