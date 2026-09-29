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
    'GET /api/public/plans' => ['PublicGymController', 'plansCatalog'],
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
    'GET /api/class-sessions'                       => ['AgendaController', 'memberSessions'],
    'GET /api/bookings/mine'                        => ['AgendaController', 'myBookings'],
    'GET /api/bookings/(\d+)/calendar'              => ['CalendarioController', 'show'],
    'GET /api/bookings/(\d+)/calendar[.]ics'        => ['CalendarioController', 'download'],
    'POST /api/class-sessions/(\d+)/book'          => ['AgendaController', 'memberBook'],
    'DELETE /api/bookings/(\d+)'                  => ['AgendaController', 'memberCancel'],
    'GET /api/payments/mine'                        => ['PagoController', 'memberList'],
    'POST /api/payments/checkout'                   => ['PagoController', 'checkout'],
    'GET /api/membresia/mia'         => ['MembresiaController', 'mia'],
    'POST /api/membresia/activar'    => ['MembresiaController', 'activar'],
    'POST /api/membresia/suspender'  => ['MembresiaController', 'suspender'],
    'GET /api/membresia/todas'       => ['MembresiaController', 'todas'],
    'GET /api/me/notifications'                      => ['NotificacionController', 'index'],
    'PATCH /api/me/notifications/(\d+)/read'        => ['NotificacionController', 'read'],
    'POST /api/me/notifications/read-all'            => ['NotificacionController', 'readAll'],
    'GET /api/me/notification-preferences'           => ['NotificacionController', 'preferences'],
    'PUT /api/me/notification-preferences'           => ['NotificacionController', 'updatePreferences'],
    'POST /api/notifications/unsubscribe'             => ['NotificacionController', 'unsubscribe'],

    // Experiencia integral del socio: perfil, preferencias, progreso y carné.
    'GET /api/member/summary'                         => ['SocioController', 'summary'],
    'GET /api/member/profile'                         => ['SocioController', 'profile'],
    'PATCH /api/member/profile'                       => ['SocioController', 'updateProfile'],
    'POST /api/member/profile/photo'                  => ['SocioController', 'uploadPhoto'],
    'GET /api/member/avatar'                          => ['SocioController', 'avatar'],
    'GET /api/member/preferences'                     => ['SocioController', 'preferences'],
    'PUT /api/member/preferences'                     => ['SocioController', 'updatePreferences'],
    'GET /api/member/favorites'                       => ['SocioController', 'favorites'],
    'POST /api/member/favorites'                      => ['SocioController', 'addFavorite'],
    'DELETE /api/member/favorites/(gym|activity)/(\d+)' => ['SocioController', 'removeFavorite'],
    'GET /api/member/attendance'                      => ['SocioController', 'attendance'],
    'GET /api/member/memberships'                     => ['SocioController', 'memberships'],
    'GET /api/member/measurements'                    => ['SocioController', 'measurements'],
    'POST /api/member/measurements'                   => ['SocioController', 'createMeasurement'],
    'GET /api/member/card'                            => ['SocioController', 'card'],

    // Admin — requiere rol admin
    'GET /api/admin/context'                        => ['AdminApiController', 'context'],
    'GET /api/admin/geocoding'                      => ['GeocodificacionController', 'search'],
    'POST /api/admin/context/select'                => ['AdminApiController', 'selectContext'],
    'GET /api/admin/summary'                        => ['AdminApiController', 'summary'],
    'GET /api/admin/activity'                       => ['AdminApiController', 'activity'],
    'GET /api/admin/permissions'                    => ['AdminApiController', 'permissions'],
    'POST /api/admin/member-card/verify'            => ['SocioController', 'verifyCard'],
    'GET /api/admin/members'                        => ['AdminApiController', 'members'],
    'GET /api/admin/staff'                          => ['AdminApiController', 'staff'],
    'GET /api/admin/classes'                        => ['AdminApiController', 'classes'],
    'GET /api/admin/reservations'                   => ['AdminApiController', 'reservations'],
    'GET /api/admin/memberships'                    => ['AdminApiController', 'memberships'],
    'GET /api/admin/payments'                       => ['PagoController', 'adminList'],
    'GET /api/admin/payments/options'               => ['PagoController', 'adminOptions'],
    'POST /api/admin/payments/manual'               => ['PagoController', 'createManual'],
    'POST /api/admin/payments/(\d+)/refund'        => ['PagoController', 'refund'],
    'GET /api/admin/finance'                        => ['PagoController', 'finance'],
    'GET /api/admin/promotions'                     => ['PromocionController', 'index'],
    'POST /api/admin/promotions'                    => ['PromocionController', 'create'],
    'GET /api/admin/promotions/(\d+)'              => ['PromocionController', 'show'],
    'PATCH /api/admin/promotions/(\d+)'            => ['PromocionController', 'update'],
    'DELETE /api/admin/promotions/(\d+)'           => ['PromocionController', 'delete'],
    'POST /api/admin/promotions/(\d+)/schedule'    => ['PromocionController', 'schedule'],
    'POST /api/admin/promotions/(\d+)/pause'       => ['PromocionController', 'pause'],
    'POST /api/admin/promotions/(\d+)/finish'      => ['PromocionController', 'finish'],
    'GET /api/admin/promotions/(\d+)/results'      => ['PromocionController', 'results'],
    'GET /api/admin/exports'                        => ['AdminApiController', 'exports'],
    'POST /api/admin/exports'                       => ['ReporteController', 'generate'],
    'GET /api/admin/exports/(\d+)/download'        => ['ReporteController', 'download'],
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
    'GET /api/admin/schedule'                       => ['AgendaController', 'adminSessions'],
    'GET /api/admin/schedule/options'               => ['AgendaController', 'adminOptions'],
    'POST /api/admin/class-definitions'             => ['AgendaController', 'createClass'],
    'PATCH /api/admin/class-definitions/(\d+)'     => ['AgendaController', 'updateClass'],
    'POST /api/admin/class-definitions/(\d+)/sessions' => ['AgendaController', 'createSession'],
    'PATCH /api/admin/class-sessions/(\d+)'        => ['AgendaController', 'updateSession'],
    'POST /api/admin/class-sessions/(\d+)/cancel'  => ['AgendaController', 'cancelSession'],
    'GET /api/admin/class-sessions/(\d+)/roster'   => ['AgendaController', 'roster'],
    'POST /api/admin/class-sessions/(\d+)/bookings' => ['AgendaController', 'adminBook'],
    'DELETE /api/admin/bookings/(\d+)'             => ['AgendaController', 'adminCancelBooking'],
    'PUT /api/admin/bookings/(\d+)/attendance'     => ['AgendaController', 'attendance'],

    // Endpoints heredados conservados por compatibilidad.
    'GET /api/admin/socios'                         => ['AdminController', 'listarSocios'],
    'GET /api/admin/socios/(\d+)'                  => ['AdminController', 'verSocio'],
    // La mutación heredada de estado global se retiró del router: no tenía
    // aislamiento por gimnasio. El contrato moderno /api/admin/members/{id}
    // conserva las operaciones tenant con auditoría.

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

    // Webhook público: valida firma y consulta el estado al proveedor antes de persistir.
    'POST /api/webhooks/mercado-pago'               => ['PagoController', 'mercadoPagoWebhook'],
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
