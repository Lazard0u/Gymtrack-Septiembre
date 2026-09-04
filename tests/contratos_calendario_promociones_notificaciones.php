<?php
/**
 * Contrato automatizado contratos_calendario_promociones_notificaciones. Inspecciona archivos o comportamiento y finaliza con error si se rompe una garantía del proyecto.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

require_once $root . '/backend/services/IcsCalendarService.php';
require_once $root . '/backend/services/NotificationChannelInterface.php';
require_once $root . '/backend/services/InternalNotificationChannel.php';
require_once $root . '/backend/services/EmailNotificationChannel.php';
require_once $root . '/backend/services/WhatsAppNotificationChannel.php';
require_once $root . '/backend/models/NotificationRepository.php';
require_once $root . '/backend/models/PromotionRepository.php';
require_once $root . '/backend/services/NotificationDispatcher.php';
require_once $root . '/backend/controllers/CalendarController.php';
require_once $root . '/backend/controllers/NotificationController.php';
require_once $root . '/backend/controllers/PromotionController.php';

$methods = [
    CalendarController::class => ['show', 'download'],
    NotificationController::class => ['index', 'read', 'readAll', 'preferences', 'updatePreferences', 'unsubscribe'],
    PromotionController::class => ['index', 'show', 'create', 'update', 'delete', 'schedule', 'pause', 'finish', 'results'],
    NotificationRepository::class => [
        'inbox', 'markRead', 'markAllRead', 'preferences', 'updatePreferences', 'unsubscribe',
        'claimNext', 'refreshPromotionStates', 'consentAllows', 'createInternal', 'unsubscribeUrl',
        'delivered', 'skipped', 'failed', 'recoverStale',
    ],
    PromotionRepository::class => ['list', 'find', 'create', 'update', 'delete', 'schedule', 'pause', 'finish', 'results'],
    NotificationDispatcher::class => ['dispatch'],
];
foreach ($methods as $class => $required) {
    foreach ($required as $method) {
        if (!method_exists($class, $method)) {
            $failures[] = "Falta {$class}::{$method}().";
        }
    }
}

$routes = file_get_contents($root . '/backend/routes/api.php');
foreach ([
    "'GET /api/bookings/(\\d+)/calendar'",
    "'GET /api/bookings/(\\d+)/calendar[.]ics'",
    "'GET /api/me/notifications'",
    "'PATCH /api/me/notifications/(\\d+)/read'",
    "'POST /api/me/notifications/read-all'",
    "'GET /api/me/notification-preferences'",
    "'PUT /api/me/notification-preferences'",
    "'POST /api/notifications/unsubscribe'",
    "'GET /api/admin/promotions'",
    "'POST /api/admin/promotions'",
    "'PATCH /api/admin/promotions/(\\d+)'",
    "'POST /api/admin/promotions/(\\d+)/schedule'",
    "'POST /api/admin/promotions/(\\d+)/pause'",
    "'POST /api/admin/promotions/(\\d+)/finish'",
    "'GET /api/admin/promotions/(\\d+)/results'",
] as $route) {
    if (!str_contains($routes, $route)) {
        $failures[] = "Falta la ruta {$route}.";
    }
}

$migration = file_get_contents($root . '/database/migrations/008_phase9_calendar_promotions_notifications.sql');
$rollback = file_get_contents($root . '/database/migrations/008_phase9_calendar_promotions_notifications.down.sql');
foreach ([
    'promociones', 'promocion_gimnasios', 'notificacion_preferencias',
    'marketing_unsubscribe_tokens', 'notificacion_envios', 'idempotency_key',
    'uq_notification_delivery_idempotency', 'promotions.read', 'promotions.write',
    '008_phase9_calendar_promotions_notifications',
] as $needle) {
    if (!str_contains($migration, $needle)) {
        $failures[] = "La migración 008 no declara {$needle}.";
    }
}
foreach ([
    'DROP TABLE IF EXISTS notificacion_envios',
    'DROP TABLE IF EXISTS promociones',
    'gymtrack_phase9_rollback_guard',
] as $needle) {
    if (!str_contains($rollback, $needle)) {
        $failures[] = "El rollback 008 no contempla {$needle}.";
    }
}

$promotionSource = file_get_contents($root . '/backend/models/PromotionRepository.php');
$notificationSource = file_get_contents($root . '/backend/models/NotificationRepository.php');
$calendarSource = file_get_contents($root . '/backend/controllers/CalendarController.php');
foreach (['(p.demo_dataset_id <=> ?)', '(pg.demo_dataset_id <=> ?)', 'idempotency_key LIKE ?', 'INSERT IGNORE INTO notificacion_envios'] as $needle) {
    if (!str_contains($promotionSource, $needle)) {
        $failures[] = "PromotionRepository no conserva el contrato {$needle}.";
    }
}
if (str_contains($promotionSource, "'recipient_email'=>") || str_contains($promotionSource, "'recipient_name'=>")) {
    $failures[] = 'La cola promocional persiste datos personales redundantes en payload_json.';
}
foreach (['FOR UPDATE SKIP LOCKED', '(demo_dataset_id <=> ?)', 'marketing_consent', 'recoverStale', 'token_hash'] as $needle) {
    if (!str_contains($notificationSource, $needle)) {
        $failures[] = "NotificationRepository no conserva el contrato {$needle}.";
    }
}
foreach (['r.usuario_id=?', 's.gimnasio_id=?', 'r.demo_dataset_id <=> ?', 's.demo_dataset_id <=> ?', 'g.demo_dataset_id <=> ?'] as $needle) {
    if (!str_contains($calendarSource, $needle)) {
        $failures[] = "CalendarController no cierra el alcance {$needle}.";
    }
}
if (preg_match('/\$this->pdo->query\s*\(/', $promotionSource . $notificationSource)) {
    $failures[] = 'Los repositorios de promociones y notificaciones contienen una consulta sin prepared statement.';
}
if (!str_contains(file_get_contents($root . '/backend/services/WhatsAppNotificationChannel.php'), 'whatsapp_adapter_unavailable')) {
    $failures[] = 'WhatsApp no falla cerrado cuando falta el adaptador.';
}
$console = file_get_contents($root . '/backend/console.php');
if (!str_contains($console, "strtolower((string)(getenv('APP_ENV')?:'production'))!=='production'")
    || !str_contains($console, 'new NotificationDispatcher(null,$allDatasets)')) {
    $failures[] = 'El worker podría procesar datos demo automáticamente en producción.';
}

$booking = [
    'booking_id' => 41,
    'sesion_clase_id' => 73,
    'inicio_en' => '2026-08-18 09:00:00',
    'fin_en' => '2026-08-18 10:15:00',
    'zona_horaria' => 'America/Montevideo',
    'version' => 4,
    'nombre' => 'Fuerza, movilidad y técnica',
    'descripcion' => "Trabajo progresivo; sin diagnóstico.\nBEGIN:VALARM",
    'instructor_nombre' => 'Valentina Demo',
    'gimnasio_nombre' => 'GymTrack Centro',
    'sede_nombre' => 'Sede principal',
    'direccion' => 'Avenida Ficticia 1234',
    'ciudad' => 'Montevideo',
    'departamento' => 'Montevideo',
];
$calendar = new IcsCalendarService();
$payload = $calendar->payload($booking);
$ics = $calendar->render($booking);
if ($payload['uid'] !== 'gymtrack-booking-41-session-73@gymtrack.local') {
    $failures[] = 'El UID de calendario no es estable por reserva y sesión.';
}
if ($payload['start_at'] !== '2026-08-18T09:00:00-03:00'
    || !str_contains($payload['google_calendar_url'], '20260818T120000Z%2F20260818T131500Z')) {
    $failures[] = 'La exportación no conserva la zona horaria y el instante UTC correctos.';
}
foreach ([
    "BEGIN:VCALENDAR\r\n",
    'UID:gymtrack-booking-41-session-73@gymtrack.local',
    'DTSTART;TZID=America/Montevideo:20260818T090000',
    'DTEND;TZID=America/Montevideo:20260818T101500',
    'SEQUENCE:4',
    'SUMMARY:Fuerza\\, movilidad y técnica',
    'STATUS:CONFIRMED',
] as $needle) {
    if (!str_contains($ics, $needle)) {
        $failures[] = "El ICS no contiene {$needle}.";
    }
}
if (preg_match('/(?<!\r)\n/', $ics)) {
    $failures[] = 'El ICS contiene saltos de línea que no son CRLF.';
}
if (str_contains($ics, "\r\nBEGIN:VALARM\r\n")) {
    $failures[] = 'El ICS permite inyectar propiedades desde la descripción.';
}
foreach (explode("\r\n", rtrim($ics, "\r\n")) as $line) {
    if (strlen($line) > 75) {
        $failures[] = 'El ICS supera el límite de 75 octetos por línea.';
        break;
    }
}
if (($payload['automatic_sync']['enabled'] ?? true) !== false
    || !str_contains((string) ($payload['automatic_sync']['message'] ?? ''), 'exportación manual funciona')) {
    $failures[] = 'Google Calendar automático no comunica su alcance beta con honestidad.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contratos de calendario, promociones y notificaciones correctos.\n";
