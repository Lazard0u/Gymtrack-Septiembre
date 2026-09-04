<?php
/**
 * Contrato automatizado integrity_contracts. Inspecciona archivos o comportamiento y finaliza con error si se rompe una garantía del proyecto.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/backend/models/Clase.php';
require_once $root . '/backend/models/Reserva.php';
require_once $root . '/backend/models/Membresia.php';
require_once $root . '/backend/middleware/AuthMiddleware.php';

$contratos = [
    Clase::class => [
        'listarActivas', 'listarTodas', 'buscarPorId', 'crear', 'actualizar',
        'eliminar', 'estadisticas', 'listarInscriptos',
    ],
    Reserva::class => ['reservar', 'cancelar', 'listarPorUsuario', 'listarTodas'],
    Membresia::class => [
        'actualizarVencidas', 'tieneMembresiaActiva', 'obtenerPorUsuario',
        'activar', 'suspender', 'listarTodas',
    ],
    AuthMiddleware::class => ['verificarSesion', 'verificarRol', 'destruirSesionActual'],
];

$fallos = [];
foreach ($contratos as $clase => $metodos) {
    foreach ($metodos as $metodo) {
        if (!method_exists($clase, $metodo)) {
            $fallos[] = "Falta {$clase}::{$metodo}()";
        }
    }
}

$rutas = file_get_contents($root . '/backend/routes/api.php');
$rutasRequeridas = [
    "'GET /api/clases/(\\d+)'",
    "'PUT /api/clases/(\\d+)'",
    "'DELETE /api/clases/(\\d+)'",
    "'DELETE /api/reservas/(\\d+)'",
];
foreach ($rutasRequeridas as $ruta) {
    if (!str_contains($rutas, $ruta)) {
        $fallos[] = "Falta la ruta dinámica {$ruta}";
    }
}

$auth = file_get_contents($root . '/backend/controllers/AuthController.php');
if (str_contains($auth, 'hash_equals($password, $hash)')) {
    $fallos[] = 'El login todavía permite contraseñas en texto plano.';
}

if ($fallos) {
    fwrite(STDERR, implode(PHP_EOL, $fallos) . PHP_EOL);
    exit(1);
}

echo "Contratos de integridad correctos.\n";
