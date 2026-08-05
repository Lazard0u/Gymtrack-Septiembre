<?php
/**
 * GymTrack · Backend API REST
 * ---------------------------------------------------------------
 * Punto de entrada único del sistema. Toda petición HTTP pasa
 * por acá antes de llegar a los controladores.
 *
 * Flujo de una petición:
 *   Navegador (Vue) → index.php → Router → Controller → Model → DB
 *
 * Tecnología Web Aplicada · Año lectivo 2026
 * Leandro González · Santiago Cáceres · Máximo Díaz · Emilio Escobar
 */

// ── 1. Errores ────────────────────────────────────────────────
// En producción los detalles quedan únicamente en el log del servidor.
$entorno = getenv('APP_ENV') ?: 'production';
ini_set('display_errors', $entorno === 'development' ? '1' : '0');
error_reporting(E_ALL);

// ── 2. Headers CORS ───────────────────────────────────────────
// Vue corre en localhost:5173 y PHP en localhost:8080.
// Sin estos headers el navegador bloquea la comunicación
// por la política Same-Origin.
$origenPermitido = getenv('FRONTEND_URL') ?: 'http://localhost:5173';
$origenPeticion = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origenPeticion !== '' && hash_equals($origenPermitido, $origenPeticion)) {
    header("Access-Control-Allow-Origin: {$origenPermitido}");
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Type: application/json; charset=UTF-8');

set_exception_handler(function (Throwable $error) use ($entorno): void {
    error_log(sprintf(
        '[GymTrack] %s in %s:%d',
        $error->getMessage(),
        $error->getFile(),
        $error->getLine()
    ));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }

    $respuesta = [
        'error' => true,
        'mensaje' => 'Ocurrió un error inesperado. Intentá nuevamente.',
    ];
    if ($entorno === 'development') {
        $respuesta['detalle'] = $error->getMessage();
    }

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
});

// Las peticiones OPTIONS son "preflight" del navegador —
// las respondemos vacías con 204 y cerramos.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── 3. Autoload de clases ─────────────────────────────────────
// En lugar de hacer require_once en cada archivo, registramos
// una función que carga automáticamente la clase que se necesite.
spl_autoload_register(function (string $clase) {
    // Buscamos en config/, controllers/, models/ y middleware/
    $carpetas = ['config', 'controllers', 'models', 'middleware'];

    foreach ($carpetas as $carpeta) {
        $ruta = __DIR__ . "/{$carpeta}/{$clase}.php";
        if (file_exists($ruta)) {
            require_once $ruta;
            return;
        }
    }
});

// ── 4. Router ─────────────────────────────────────────────────
// Leemos la URL y el método HTTP para decidir qué controlador ejecutar.
require_once __DIR__ . '/routes/api.php';
