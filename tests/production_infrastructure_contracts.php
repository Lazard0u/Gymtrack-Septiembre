<?php
/**
 * Contrato automatizado production_infrastructure_contracts. Inspecciona archivos o comportamiento y finaliza con error si se rompe una garantía del proyecto.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$requiredFiles = [
    'backend/Dockerfile.prod',
    'backend/.dockerignore',
    'frontend/Dockerfile.prod',
    'frontend/.dockerignore',
    'frontend/nginx.prod.conf',
    'docker-compose.prod.yml',
    'scripts/production-preflight.sh',
    '.github/workflows/ci.yml',
];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Falta {$file}.";
    }
}

$compose = file_get_contents($root . '/docker-compose.prod.yml');
$backendDockerfile = file_get_contents($root . '/backend/Dockerfile.prod');
$frontendDockerfile = file_get_contents($root . '/frontend/Dockerfile.prod');
$nginx = file_get_contents($root . '/frontend/nginx.prod.conf');
$preflight = file_get_contents($root . '/scripts/production-preflight.sh');
$ci = file_get_contents($root . '/.github/workflows/ci.yml');

foreach (['${APP_KEY:?', '${DB_PASSWORD:?', '${MYSQL_ROOT_PASSWORD:?', '${TURNSTILE_SECRET_KEY:?', '${SMTP_CONFIG_FILE:?', '${MERCADO_PAGO_ACCESS_TOKEN:?', '${MERCADO_PAGO_WEBHOOK_SECRET:?'] as $requiredSecret) {
    if (!str_contains($compose, $requiredSecret)) {
        $failures[] = "docker-compose.prod.yml no falla cerrado para {$requiredSecret}.";
    }
}
foreach (['APP_ENV: production', 'SEED_DEMO_DATA: "false"', 'ALLOW_DEMO_DATA_IN_PRODUCTION: "false"', 'SESSION_SECURE: "true"', 'TRUST_PROXY_HEADERS: "true"'] as $productionSetting) {
    if (!str_contains($compose, $productionSetting)) {
        $failures[] = "Falta el ajuste de producción {$productionSetting}.";
    }
}

if (!preg_match('/^  db:\n(?<body>.*?)(?=^  [a-z][a-z0-9-]*:\n)/ms', $compose, $dbMatch)) {
    $failures[] = 'No se pudo aislar la definición del servicio db.';
} elseif (preg_match('/^\s+ports:/m', $dbMatch['body'])) {
    $failures[] = 'MySQL publica un puerto del host en producción.';
}
if (!str_contains($compose, "http://127.0.0.1/api/system/context")) {
    $failures[] = 'El healthcheck del backend no usa el endpoint existente de contexto.';
}
if (!str_contains($compose, 'mysql:8.0@sha256:')
    || !str_contains($backendDockerfile, 'php:8.2-apache@sha256:')
    || !str_contains($frontendDockerfile, 'node:20-alpine@sha256:')
    || !str_contains($frontendDockerfile, 'nginx:1.27-alpine@sha256:')) {
    $failures[] = 'Las imágenes de producción no están fijadas por digest.';
}
if (!str_contains($compose, 'condition: service_healthy') || substr_count($compose, 'healthcheck:') < 2) {
    $failures[] = 'Los servicios no esperan dependencias saludables.';
}
if (substr_count($compose, 'read_only: true') < 2 || substr_count($compose, 'no-new-privileges:true') < 2) {
    $failures[] = 'Los contenedores de aplicación no cierran escritura y escalada de privilegios.';
}
if (!str_contains($compose, 'max-size: 10m') || !str_contains($compose, 'max-file: "5"')) {
    $failures[] = 'Los logs de contenedor no tienen rotación acotada.';
}
foreach (['008_phase9_calendar_promotions_notifications.sql', '009_phase10_member_experience.sql', '010_gimnasios_mapa_sincronizado.sql'] as $migration) {
    if (!str_contains($compose, $migration)) {
        $failures[] = "La base nueva no aplica {$migration}.";
    }
}
if (!str_contains($compose, 'schema-check:')
    || !str_contains($compose, 'condition: service_completed_successfully')
    || !str_contains($compose, 'Falta la migración requerida:')) {
    $failures[] = 'Producción no falla cerrado frente a una base con migraciones pendientes.';
}
if (!str_contains($compose, 'notification-worker:')
    || !str_contains($compose, 'notifications:dispatch 100')
    || !str_contains($compose, 'maintenance-worker:')
    || !str_contains($compose, 'auth:cleanup')) {
    $failures[] = 'Producción no ejecuta las colas y tareas de mantenimiento requeridas.';
}

foreach (['php:8.2-apache', 'pdo_mysql', 'opcache', 'display_errors=Off', 'session.cookie_secure=1', 'session.save_path=/var/lib/php/sessions', 'session.gc_maxlifetime=28800', 'upload_max_filesize=5M', 'msmtp --file=/run/secrets/smtp_config', '"mbstring","curl","fileinfo"', 'apache2ctl -t'] as $backendContract) {
    if (!str_contains($backendDockerfile, $backendContract)) {
        $failures[] = "Dockerfile.prod del backend omite {$backendContract}.";
    }
}
if (!str_contains($frontendDockerfile, 'FROM node:20-alpine@sha256:')
    || !str_contains($frontendDockerfile, ' AS build')
    || !str_contains($frontendDockerfile, 'FROM nginx:1.27-alpine@sha256:')
    || !str_contains($frontendDockerfile, 'npm ci --no-audit --no-fund')
    || !str_contains($frontendDockerfile, 'npm run build')) {
    $failures[] = 'El frontend no usa un build multi-stage reproducible.';
}

foreach (['proxy_pass http://backend;', 'try_files $uri $uri/ /index.html;', 'Content-Security-Policy', 'Strict-Transport-Security', 'X-Content-Type-Options', 'client_max_body_size 6m'] as $nginxContract) {
    if (!str_contains($nginx, $nginxContract)) {
        $failures[] = "nginx.prod.conf omite {$nginxContract}.";
    }
}

foreach (['APP_KEY debe tener al menos 32', 'MYSQL_ROOT_PASSWORD debe tener al menos 16', 'debe usar HTTPS', 'SEED_DEMO_DATA', 'GEOCODING_USER_AGENT', 'EMAIL_VERIFICATION_TTL_SECONDS', 'PAYMENT_MODE', 'config --quiet'] as $preflightContract) {
    if (!str_contains($preflight, $preflightContract)) {
        $failures[] = "El preflight omite el contrato {$preflightContract}.";
    }
}

foreach (['npm audit --omit=dev --audit-level=high', 'npm run test:run', 'npm run build', "tests/*_contracts.php", 'php -l', 'production-preflight.sh', 'docker build --file backend/Dockerfile.prod'] as $ciContract) {
    if (!str_contains($ci, $ciContract)) {
        $failures[] = "CI omite {$ciContract}.";
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contratos de infraestructura de producción correctos.\n";
