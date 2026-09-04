<?php
/**
 * Contrato automatizado contratos_miembro. Inspecciona archivos o comportamiento y finaliza con error si se rompe una garantía del proyecto.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

require_once $root . '/backend/services/MemberCardService.php';
require_once $root . '/backend/models/MemberRepository.php';
require_once $root . '/backend/controllers/MemberController.php';

$methods = [
    MemberController::class => [
        'summary','profile','updateProfile','uploadPhoto','avatar','preferences','updatePreferences',
        'favorites','addFavorite','removeFavorite','attendance','memberships','measurements','createMeasurement',
        'card','verifyCard',
    ],
    MemberRepository::class => [
        'memberContext','gymInScope','summary','profile','updateProfile','saveAvatar','avatarFile','preferences',
        'updatePreferences','favorites','addFavoriteGym','addFavoriteActivity','removeFavorite','attendanceHistory',
        'memberships','measurements','createMeasurement','cardRecord','verifyCardMember',
    ],
    MemberCardService::class => ['issue','verify'],
];
foreach ($methods as $class => $required) {
    foreach ($required as $method) {
        if (!method_exists($class,$method)) $failures[] = "Falta {$class}::{$method}().";
    }
}

$migration = file_get_contents($root . '/database/migrations/009_phase10_member_experience.sql');
$rollback = file_get_contents($root . '/database/migrations/009_phase10_member_experience.down.sql');
foreach ([
    'socio_avatar','foto_archivo_id','fk_usuario_foto','socio_preferencias','actividades',
    'socio_gimnasios_favoritos','socio_actividades_favoritas','socio_mediciones',
    'horarios_preferidos_json','is_demo','demo_dataset_id','chk_socio_medicion_valor',
    '009_phase10_member_experience',
] as $needle) {
    if (!str_contains($migration,$needle)) $failures[] = "La migración 009 no declara {$needle}.";
}
foreach (['DROP TABLE IF EXISTS socio_mediciones','DROP TABLE IF EXISTS socio_preferencias','DROP FOREIGN KEY fk_usuario_foto'] as $needle) {
    if (!str_contains($rollback,$needle)) $failures[] = "El rollback 009 no contempla {$needle}.";
}

$repository = file_get_contents($root . '/backend/models/MemberRepository.php');
if (substr_count($repository,'(demo_dataset_id <=> ?)') < 12 || substr_count($repository,'gimnasio_id=?') < 12) {
    $failures[] = 'MemberRepository no aplica scope de gimnasio y dataset de forma sistemática.';
}
if (preg_match('/\$this->pdo->query\s*\(/',$repository)) {
    $failures[] = 'MemberRepository contiene una consulta sin prepared statement.';
}
foreach (['bajo peso','sobrepeso','obesidad','peso normal','diagnóstico de'] as $diagnosis) {
    if (stripos($repository,$diagnosis) !== false) $failures[] = "Las mediciones incluyen una clasificación clínica no permitida: {$diagnosis}.";
}
if (!str_contains($repository,'no constituye un diagnostico medico')) {
    $failures[] = 'Las respuestas de IMC no incluyen el aviso orientativo.';
}

$controller = file_get_contents($root . '/backend/controllers/MemberController.php');
foreach ([
    'GET   /api/member/summary','PATCH /api/member/profile','POST  /api/member/profile/photo',
    'GET   /api/member/favorites','GET   /api/member/attendance','GET   /api/member/measurements',
    'GET   /api/member/card','POST  /api/admin/member-card/verify',
] as $routeContract) {
    if (!str_contains($controller,$routeContract)) $failures[] = "Falta documentar {$routeContract}.";
}
if (!str_contains($controller,'MAX_PHOTO_BYTES = 5_242_880') || !str_contains($controller,'getimagesize')) {
    $failures[] = 'La carga de avatar no conserva límite de 5 MB y validación real de imagen.';
}
if (!str_contains($controller,"verificarPermiso('members.read')") || !str_contains($controller,'member_card_scope_mismatch')) {
    $failures[] = 'La verificación administrativa del carné no cierra permiso y tenant.';
}
if (!str_contains($controller,"['morning','afternoon','evening']")
    || !str_contains($repository,'horarios_preferidos_json')) {
    $failures[] = 'Los horarios preferidos no tienen validación cerrada y persistencia JSON.';
}

$cards = new MemberCardService(str_repeat('secreto-prueba-miembro-',3),120);
$issued = $cards->issue(17,4,91,true,3);
$verified = $cards->verify($issued['token']);
if ($verified['user_id'] !== 17 || $verified['gym_id'] !== 4 || $verified['membership_id'] !== 91
    || !$verified['is_demo'] || $verified['dataset_id'] !== 3) {
    $failures[] = 'El carné firmado no conserva su scope técnico.';
}
$payload = json_decode(base64_decode(strtr(explode('.',$issued['token'])[1] . '===','-_','+/')),true);
foreach (['name','nombre','email','phone','telefono','member_number','numero_socio'] as $piiKey) {
    if (array_key_exists($piiKey,$payload)) $failures[] = "El token del carné expone PII: {$piiKey}.";
}
$tampered = substr($issued['token'],0,-1) . (substr($issued['token'],-1)==='a'?'b':'a');
try {
    $cards->verify($tampered);
    $failures[] = 'El carné acepta una firma alterada.';
} catch (InvalidArgumentException) {
    // Correcto: la firma alterada se rechaza.
}

// api.php pertenece al integrador. Cuando incorpore MemberController, el contrato
// pasa a exigir todas las rutas; antes de eso informa el bloqueo sin fallar este módulo aislado.
$routes = file_get_contents($root . '/backend/routes/api.php');
if (str_contains($routes,'MemberController')) {
    foreach ([
        "'GET /api/member/summary'","'GET /api/member/profile'","'PATCH /api/member/profile'",
        "'POST /api/member/profile/photo'","'GET /api/member/avatar'","'GET /api/member/preferences'",
        "'PUT /api/member/preferences'","'GET /api/member/favorites'","'POST /api/member/favorites'",
        "'GET /api/member/attendance'","'GET /api/member/memberships'","'GET /api/member/measurements'",
        "'POST /api/member/measurements'","'GET /api/member/card'","'POST /api/admin/member-card/verify'",
    ] as $route) {
        if (!str_contains($routes,$route)) $failures[] = "La integración de rutas omite {$route}.";
    }
} else {
    echo "Falta integrar MemberController en api.php.\n";
}

$compose = file_get_contents($root . '/docker-compose.yml');
if (!str_contains($compose,'009_phase10_member_experience.sql:/docker-entrypoint-initdb.d/009_phase10_member_experience.sql:ro')) {
    $failures[] = 'docker-compose.yml no monta la migración 009 en instalaciones nuevas.';
}

$seeder = file_get_contents($root . '/backend/seeders/DemoDatasetSeeder.php');
foreach ([
    // El seeder exige la migración más reciente; esa migración se aplica después de la 009
    // y por eso garantiza indirectamente que el esquema de experiencia del socio ya existe.
    "private const REQUIRED_MIGRATION = '010_gimnasios_mapa_sincronizado'",
    'upsertMemberExperience','upsertAttendanceHistory','horarios_preferidos_json',
    'socio_gimnasios_favoritos','socio_actividades_favoritas','socio_mediciones',
] as $needle) {
    if (!str_contains($seeder,$needle)) $failures[] = "El dataset demo del área del socio omite {$needle}.";
}
foreach (['socio_actividades_favoritas','socio_gimnasios_favoritos','socio_mediciones','socio_preferencias','actividades'] as $table) {
    if (substr_count($seeder,$table) < 2) $failures[] = "La limpieza demo no contempla {$table}.";
}

if ($failures) {
    fwrite(STDERR,implode(PHP_EOL,$failures) . PHP_EOL);
    exit(1);
}

echo "Contratos de experiencia del socio correctos.\n";
