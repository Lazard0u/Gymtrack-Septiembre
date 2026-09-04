<?php
/**
 * Contrato automatizado security_tenant_contracts. Inspecciona archivos o comportamiento y finaliza con error si se rompe una garantía del proyecto.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$routes = file_get_contents($root . '/backend/routes/api.php');
if (str_contains($routes, "'PUT /api/admin/socios/(\\d+)/estado'")) {
    $failures[] = 'La mutación heredada de socios continúa expuesta.';
}

$roleRestricted = [
    $root . '/backend/controllers/ClasesController.php' => ['todas', 'stats'],
    $root . '/backend/controllers/ReservasController.php' => ['todas'],
    $root . '/backend/controllers/MembresiaController.php' => ['todas'],
];
foreach ($roleRestricted as $file => $methods) {
    $source = file_get_contents($file);
    foreach ($methods as $method) {
        if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\([^)]*\).*?verificarRoles\s*\(/s', $source)) {
            $failures[] = basename($file) . "::{$method} no restringe los roles operativos.";
        }
    }
}

foreach (['Clase.php', 'Reserva.php', 'Membresia.php'] as $model) {
    $source = file_get_contents($root . '/backend/models/' . $model);
    if (!str_contains($source, 'private function requireGym') || !str_contains($source, 'contexto de gimnasio explícito')) {
        $failures[] = "{$model} no falla cerrado sin contexto de gimnasio.";
    }
}

$me = file_get_contents($root . '/backend/controllers/MeController.php');
if (!str_contains($me, "gymAssignments(\$userId)!==[]") || !str_contains($me, "'gym_context_required'")) {
    $failures[] = 'Las cuentas tenant todavía pueden limpiar su contexto activo.';
}

$legacyAdmin = file_get_contents($root . '/backend/controllers/AdminController.php');
$users = file_get_contents($root . '/backend/models/Usuario.php');
if (!str_contains($legacyAdmin, 'requerirContextoGimnasio()')
    || !str_contains($legacyAdmin, 'member.legacy_status.changed')
    || !str_contains($users, 'usuario_gimnasio_roles')) {
    $failures[] = 'La mutación heredada no conserva scope y auditoría defensivos.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contratos críticos de tenant correctos.\n";
