<?php

declare(strict_types=1);

/**
 * Puerta de entrada de seguridad para controladores protegidos.
 *
 * Cada método termina la petición cuando falta una condición. De esta forma
 * ningún endpoint depende de que Vue haya ocultado correctamente un botón.
 */
final class AuthMiddleware
{
    /** Abre la sesión y exige CSRF en toda operación que modifica datos. */
    public static function verificarSesion(): void
    {
        (new SessionManager())->start(true);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            (new SessionManager())->verifyCsrf();
        }
    }

    /** Se usa cuando cualquier rol autenticado puede acceder, pero debe verificar correo. */
    public static function verificarEmail(): void
    {
        self::verificarSesion();
        self::asegurarEmailVerificado();
    }

    /** Consulta el estado actual en MySQL y no confía en una sesión antigua. */
    private static function asegurarEmailVerificado(): void
    {
        $stmt = Database::conectar()->prepare(
            'SELECT email_verificado_en FROM usuarios WHERE id=? AND activo=1'
        );
        $stmt->execute([self::obtenerUsuarioId()]);
        $verifiedAt = $stmt->fetchColumn();
        if ($verifiedAt === false || $verifiedAt === null) {
            self::denegar(403, 'Verificá tu correo electrónico para continuar.', 'email_not_verified');
        }
    }

    /** Exige un rol efectivo exacto dentro del gimnasio activo. */
    public static function verificarRol(string $role): void
    {
        self::verificarRoles([$role]);
    }

    /** Permite cualquiera de los roles declarados por el controlador. */
    public static function verificarRoles(array $roles): void
    {
        self::verificarSesion();
        self::asegurarEmailVerificado();
        $effective = (new AuthorizationService())->effectiveRole(
            self::obtenerUsuarioId(),
            self::obtenerGimnasioContextoId()
        );
        if (!in_array($effective, $roles, true)) {
            self::denegar(403, 'No tenés permisos para acceder a este recurso.');
        }
    }

    /**
     * Comprueba una capacidad concreta; el gimnasio enviado por el cliente se
     * ignora porque el contexto autorizado vive en la sesión del servidor.
     */
    public static function verificarPermiso(string $permission, ?int $ignoredClientGymId = null): void
    {
        self::verificarSesion();
        self::asegurarEmailVerificado();
        $gymId = self::obtenerGimnasioContextoId();
        $allowed = (new AuthorizationService())->hasPermission(
            self::obtenerUsuarioId(),
            $permission,
            $gymId
        );
        if (!$allowed) {
            self::denegar(403, 'No tenés el permiso requerido para esta acción.');
        }
    }

    /** Destruye la sesión actual sólo después de validar CSRF. */
    public static function destruirSesionActual(): void
    {
        $manager = new SessionManager();
        if ($manager->start(false)) {
            $manager->verifyCsrf();
            $manager->destroy(true, 'logout');
        }
    }

    // Estos accesores mantienen el origen de verdad de identidad en $_SESSION.
    public static function obtenerUsuarioId(): int
    {
        return (int) ($_SESSION['usuario_id'] ?? 0);
    }

    /** Conservado por compatibilidad; la autorización moderna usa nombres de rol. */
    public static function obtenerRolId(): int
    {
        return 0;
    }

    public static function obtenerRolNombre(): ?string
    {
        return (new AuthorizationService())->effectiveRole(
            self::obtenerUsuarioId(),
            self::obtenerGimnasioContextoId()
        );
    }

    public static function obtenerGimnasioContextoId(): ?int
    {
        return empty($_SESSION['gimnasio_contexto_id'])
            ? null
            : (int) $_SESSION['gimnasio_contexto_id'];
    }

    /** Detiene operaciones tenant que no pueden decidirse sin gimnasio activo. */
    public static function requerirContextoGimnasio(): int
    {
        $gymId = self::obtenerGimnasioContextoId();
        if ($gymId === null) {
            self::denegar(409, 'Seleccioná un gimnasio antes de realizar esta acción.', 'gym_context_required');
        }
        return $gymId;
    }

    public static function esCuentaDemo(): bool
    {
        return (bool) ($_SESSION['is_demo'] ?? false);
    }

    public static function obtenerDemoDatasetId(): ?int
    {
        return self::esCuentaDemo() && !empty($_SESSION['demo_dataset_id'])
            ? (int) $_SESSION['demo_dataset_id']
            : null;
    }

    /** Evita que una presentación modifique datos fuera de los flujos permitidos. */
    public static function impedirMutacionDemo(): void
    {
        if (self::esCuentaDemo()) {
            self::denegar(409, 'El panel de presentación es de solo lectura para esta operación.');
        }
    }

    /** Devuelve un error seguro y agrega trazabilidad especial para administración. */
    private static function denegar(int $status, string $message, string $code = 'forbidden'): never
    {
        if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/admin')) {
            AdminAuditLogger::record(
                'admin.access.denied',
                'endpoint',
                'denied',
                self::obtenerUsuarioId() ?: null,
                self::obtenerGimnasioContextoId(),
                null,
                $code
            );
            ApiResponder::error($status, $code, $message);
        }

        http_response_code($status);
        echo json_encode(
            ['error' => true, 'codigo' => $code, 'mensaje' => $message],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}
