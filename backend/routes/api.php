<?php
/**
 * GymTrack · routes/api.php
 * ---------------------------------------------------------------
 * El router es el mapa de la API.
 * Lee la URL y el método HTTP de cada petición y decide
 * qué controlador y qué método ejecutar.
 *
 * Estructura de las rutas:
 *   MÉTODO  /api/recurso/accion  →  Controlador::metodo()
 *
 * Contratos principales disponibles:
 *   POST  /api/auth/registro  →  AuthController::registrar()
 *   POST  /api/auth/login     →  AuthController::login()
 *   POST  /api/auth/logout    →  AuthController::logout()
 *   GET   /api/perfil         →  PerfilController::ver()      (protegida)
 *   PUT   /api/perfil         →  PerfilController::actualizar() (protegida)
 *   GET/POST/PUT/DELETE /api/clases/*
 *   GET/POST/DELETE     /api/reservas/*
 *   GET/POST            /api/membresia/*
 *   GET/POST/PUT        /api/admin/*
 */

// ── Leer la URL y el método HTTP ──────────────────────────────
// REQUEST_URI tiene la URL completa, ej: /api/auth/login?foo=bar
// Quitamos los query params (?...) y normalizamos la ruta
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');          // quitamos la barra final si existe
$metodo = $_SERVER['REQUEST_METHOD']; // GET, POST, PUT, DELETE

// ── Tabla de rutas ────────────────────────────────────────────
// Formato: 'MÉTODO /ruta' => [Controlador, método]
$rutas = [
    // Autenticación — públicas (no requieren token)
    'POST /api/auth/registro' => ['AuthController', 'registrar'],
    'POST /api/auth/registro-dueno' => ['AuthController', 'registrarDueno'],
    'POST /api/auth/login'    => ['AuthController', 'login'],
    'POST /api/auth/logout'   => ['AuthController', 'logout'],
    'POST /api/auth/logout-all' => ['MeController', 'logoutAll'],
    'POST /api/auth/email/resend' => ['AuthController', 'resendEmail'],
    'POST /api/auth/email/verify' => ['AuthController', 'verifyEmail'],
    'POST /api/auth/password/forgot' => ['AuthController', 'forgotPassword'],
    'POST /api/auth/password/reset' => ['AuthController', 'resetPassword'],
    'POST /api/auth/password/change' => ['AuthController', 'changePassword'],
    'GET /api/auth/session' => ['MeController', 'probe'],

    'GET /api/me' => ['MeController', 'show'],
    'GET /api/me/sessions' => ['MeController', 'sessions'],
    'DELETE /api/me/sessions/([a-fA-F0-9-]{36})' => ['MeController', 'revokeSession'],
    'POST /api/me/gym-context' => ['MeController', 'switchGym'],
    'DELETE /api/me/gym-context' => ['MeController', 'clearGym'],

    // Contexto público de aplicación y catálogo de solo lectura.
    'GET /api/system/context'    => ['SystemController', 'context'],
    'GET /api/public/gimnasios' => ['PublicGymController', 'index'],
    'GET /api/public/gimnasios/([a-z0-9-]+)' => ['PublicGymController', 'show'],
    'GET /api/public/gyms' => ['PublicGymController', 'index'],
    'GET /api/public/gyms/([a-z0-9-]+)' => ['PublicGymController', 'show'],
    'GET /api/public/gyms/(\d+)/plans' => ['PublicGymController', 'plans'],
    'POST /api/invitations/accept' => ['AdminManagementController', 'acceptInvitation'],
    'GET /api/public/files/(\d+)' => ['AdminFileController', 'publicShow'],

    // Perfil — protegidas (requieren sesión activa)
    'GET /api/perfil'         => ['PerfilController', 'ver'],
    'PUT /api/perfil'         => ['PerfilController', 'actualizar'],

    // Clases, reservas y membresías del flujo socio.
    'GET /api/clases'                => ['ClasesController', 'listar'],
    'GET /api/clases/todas'          => ['ClasesController', 'todas'],
    'GET /api/clases/stats'          => ['ClasesController', 'stats'],
    'POST /api/clases'               => ['ClasesController', 'crear'],
    'GET /api/clases/(\d+)'         => ['ClasesController', 'ver'],
    'PUT /api/clases/(\d+)'         => ['ClasesController', 'actualizar'],
    'DELETE /api/clases/(\d+)'      => ['ClasesController', 'eliminar'],
    'POST /api/reservas'             => ['ReservasController', 'crear'],
    'DELETE /api/reservas/(\d+)'    => ['ReservasController', 'cancelar'],
    'GET /api/reservas/mias'         => ['ReservasController', 'mias'],
    'GET /api/reservas/todas'        => ['ReservasController', 'todas'],
    'GET /api/class-sessions'                       => ['ScheduleController', 'memberSessions'],
    'GET /api/bookings/mine'                        => ['ScheduleController', 'myBookings'],
    'POST /api/class-sessions/(\d+)/book'          => ['ScheduleController', 'memberBook'],
    'DELETE /api/bookings/(\d+)'                  => ['ScheduleController', 'memberCancel'],
    'GET /api/membresia/mia'         => ['MembresiaController', 'mia'],
    'POST /api/membresia/activar'    => ['MembresiaController', 'activar'],
    'POST /api/membresia/suspender'  => ['MembresiaController', 'suspender'],
    'GET /api/membresia/todas'       => ['MembresiaController', 'todas'],

    // Admin — requiere rol admin
    'GET /api/admin/context'                        => ['AdminApiController', 'context'],
    'POST /api/admin/context/select'                => ['AdminApiController', 'selectContext'],
    'GET /api/admin/summary'                        => ['AdminApiController', 'summary'],
    'GET /api/admin/activity'                       => ['AdminApiController', 'activity'],
    'GET /api/admin/permissions'                    => ['AdminApiController', 'permissions'],
    'GET /api/admin/members'                        => ['AdminApiController', 'members'],
    'GET /api/admin/staff'                          => ['AdminApiController', 'staff'],
    'GET /api/admin/classes'                        => ['AdminApiController', 'classes'],
    'GET /api/admin/reservations'                   => ['AdminApiController', 'reservations'],
    'GET /api/admin/memberships'                    => ['AdminApiController', 'memberships'],
    'GET /api/admin/payments'                       => ['AdminApiController', 'payments'],
    'GET /api/admin/exports'                        => ['AdminApiController', 'exports'],
    'POST /api/admin/gyms'                          => ['AdminManagementController', 'createGym'],
    'GET /api/admin/gyms/(\d+)'                    => ['AdminManagementController', 'showGym'],
    'PATCH /api/admin/gyms/(\d+)'                  => ['AdminManagementController', 'updateGym'],
    'DELETE /api/admin/gyms/(\d+)'                 => ['AdminManagementController', 'archiveGym'],
    'GET /api/admin/gyms/(\d+)/locations'          => ['AdminManagementController', 'locations'],
    'POST /api/admin/gyms/(\d+)/locations'         => ['AdminManagementController', 'createLocation'],
    'PATCH /api/admin/gyms/(\d+)/locations/(\d+)' => ['AdminManagementController', 'updateLocation'],
    'GET /api/admin/members/(\d+)'                 => ['AdminManagementController', 'member'],
    'PATCH /api/admin/members/(\d+)'               => ['AdminManagementController', 'updateMember'],
    'GET /api/admin/employees/(\d+)'               => ['AdminManagementController', 'employee'],
    'PATCH /api/admin/employees/(\d+)'             => ['AdminManagementController', 'updateEmployee'],
    'GET /api/admin/invitations'                    => ['AdminManagementController', 'invitations'],
    'POST /api/admin/invitations'                   => ['AdminManagementController', 'invite'],
    'DELETE /api/admin/invitations/(\d+)'          => ['AdminManagementController', 'revokeInvitation'],
    'GET /api/admin/trainers'                       => ['AdminManagementController', 'trainers'],
    'PATCH /api/admin/trainers/(\d+)'              => ['AdminManagementController', 'updateTrainer'],
    'GET /api/admin/membership-plans'               => ['AdminManagementController', 'plans'],
    'POST /api/admin/membership-plans'              => ['AdminManagementController', 'createPlan'],
    'PATCH /api/admin/membership-plans/(\d+)'      => ['AdminManagementController', 'updatePlan'],
    'POST /api/admin/memberships'                   => ['AdminManagementController', 'createMembership'],
    'PATCH /api/admin/memberships/(\d+)/status'    => ['AdminManagementController', 'transitionMembership'],
    'POST /api/admin/files'                         => ['AdminFileController', 'upload'],
    'GET /api/admin/files/(\d+)'                   => ['AdminFileController', 'show'],
    'GET /api/admin/schedule'                       => ['ScheduleController', 'adminSessions'],
    'GET /api/admin/schedule/options'               => ['ScheduleController', 'adminOptions'],
    'POST /api/admin/class-definitions'             => ['ScheduleController', 'createClass'],
    'PATCH /api/admin/class-definitions/(\d+)'     => ['ScheduleController', 'updateClass'],
    'POST /api/admin/class-definitions/(\d+)/sessions' => ['ScheduleController', 'createSession'],
    'PATCH /api/admin/class-sessions/(\d+)'        => ['ScheduleController', 'updateSession'],
    'POST /api/admin/class-sessions/(\d+)/cancel'  => ['ScheduleController', 'cancelSession'],
    'GET /api/admin/class-sessions/(\d+)/roster'   => ['ScheduleController', 'roster'],
    'POST /api/admin/class-sessions/(\d+)/bookings' => ['ScheduleController', 'adminBook'],
    'DELETE /api/admin/bookings/(\d+)'             => ['ScheduleController', 'adminCancelBooking'],
    'PUT /api/admin/bookings/(\d+)/attendance'     => ['ScheduleController', 'attendance'],

    // Contratos heredados conservados temporalmente por compatibilidad.
    'GET /api/admin/socios'                         => ['AdminController', 'listarSocios'],
    'GET /api/admin/socios/(\d+)'                  => ['AdminController', 'verSocio'],
    'PUT /api/admin/socios/(\d+)/estado'           => ['AdminController', 'actualizarEstadoSocio'],

    'GET /api/admin/membresias'                     => ['AdminController', 'listarMembresias'],
    'POST /api/admin/membresias'                    => ['AdminController', 'crearMembresia'],
    'GET /api/admin/membresias/vencidas'            => ['AdminController', 'listarMembresiasVencidas'],

    'GET /api/admin/clases'                         => ['AdminController', 'listarClases'],
    'POST /api/admin/clases'                        => ['AdminController', 'crearClase'],
    'PUT /api/admin/clases/(\d+)'                  => ['AdminController', 'actualizarClase'],
    'POST /api/admin/clases/(\d+)/cancelar'        => ['AdminController', 'cancelarClase'],
    'GET /api/admin/clases/(\d+)/inscriptos'       => ['AdminController', 'listarInscriptosClase'],

    'GET /api/admin/reservas'                       => ['AdminController', 'listarReservas'],
    'PUT /api/admin/reservas/(\d+)/cancelar'       => ['AdminController', 'cancelarReserva'],

    'GET /api/admin/estadisticas'                   => ['AdminController', 'estadisticas'],
];

// ── Resolver la ruta ──────────────────────────────────────────
$clave = "{$metodo} {$uri}";

if (array_key_exists($clave, $rutas)) {
    [$clase, $metodoAccion] = $rutas[$clave];
    if (class_exists($clase)) {
        $controlador = new $clase();
        $controlador->$metodoAccion();
        return;
    }

    http_response_code(500);
    echo json_encode([
        'error'   => true,
        'mensaje' => "Controlador '{$clase}' no encontrado."
    ]);
    return;
}

// Rutas dinámicas con parámetros (IDs) para admin
foreach ($rutas as $patron => $valor) {
    if (strpos($patron, '(') === false) {
        continue;
    }

    $regex = '#^' . $patron . '$#';
    if (preg_match($regex, $clave, $coincidencias)) {
        [$clase, $metodoAccion] = $valor;

        if (!class_exists($clase)) {
            http_response_code(500);
            echo json_encode([
                'error'   => true,
                'mensaje' => "Controlador '{$clase}' no encontrado."
            ]);
            return;
        }

        array_shift($coincidencias); // eliminamos coincidencia completa
        $controlador = new $clase();
        $controlador->$metodoAccion(...$coincidencias);
        return;
    }
}

http_response_code(404);
echo json_encode([
    'error'   => true,
    'mensaje' => "Ruta '{$uri}' no encontrada.",
    'metodo'  => $metodo
]);
