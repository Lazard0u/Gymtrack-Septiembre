<?php
/**
 * GymTrack · AuthMiddleware.php (Middleware)
 * ---------------------------------------------------------------
 * ¿Qué es un Middleware?
 *   Es un "guardián" que se ejecuta ANTES del controlador.
 *   Su trabajo es verificar que la petición cumpla ciertas condiciones
 *   antes de permitir que llegue al controlador real.
 *
 * Este middleware verifica que el usuario esté autenticado.
 * Si no lo está, devuelve un error 401 y corta la ejecución.
 *
 * Uso típico en un controlador protegido:
 *   AuthMiddleware::verificarSesion();   // Si falla, para todo
 *   AuthMiddleware::verificarRol(2);     // Solo admins (rol_id = 2)
 */

class AuthMiddleware
{
    /**
     * Verifica que exista una sesión activa válida.
     *
     * El frontend envía el token (session_id) en el header Authorization:
     *   Authorization: Bearer <session_id>
     *
     * PHP lo verifica contra las sesiones activas del servidor.
     * Si no coincide o no existe, devuelve 401 Unauthorized.
     */
    public static function verificarSesion(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name(getenv('SESSION_NAME') ?: 'gymtrack_session');
            $token = self::obtenerBearerToken();

            if ($token !== null) {
                if (!preg_match('/^[A-Za-z0-9,-]{16,128}$/', $token)) {
                    self::denegar(401, 'Token de sesión inválido.');
                }
                session_id($token);
            } elseif (empty($_COOKIE[session_name()])) {
                self::denegar(401, 'No autenticado. Iniciá sesión para continuar.');
            }

            if (!session_start()) {
                self::denegar(401, 'No se pudo validar la sesión.');
            }
        }

        // Si no hay usuario_id en la sesión, el token es inválido o expiró
        if (empty($_SESSION['usuario_id'])) {
            self::denegar(401, 'Sesión expirada. Iniciá sesión nuevamente.');
        }
    }

    /**
     * Verifica que el usuario autenticado tenga el rol requerido.
     *
     * Roles del sistema (tabla roles):
     *   1 = socio
     *   2 = admin
     *   3 = moderador
     *
     * @param int $rolRequerido  El rol_id mínimo necesario para acceder
     */
    public static function verificarRol(int $rolRequerido): void
    {
        // Este método siempre se llama después de verificarSesion(),
        // así que $_SESSION['rol_id'] ya está disponible
        $rolUsuario = (int) ($_SESSION['rol_id'] ?? 0);

        if ($rolUsuario !== $rolRequerido) {
            // 403 Forbidden: está autenticado pero no tiene permiso
            self::denegar(403, 'No tenés permisos para acceder a este recurso.');
        }
    }

    /**
     * Destruye la sesión indicada por bearer token o cookie.
     * El logout es idempotente: si no existe sesión, no crea una nueva.
     */
    public static function destruirSesionActual(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name(getenv('SESSION_NAME') ?: 'gymtrack_session');
            $token = self::obtenerBearerToken();

            if ($token !== null && preg_match('/^[A-Za-z0-9,-]{16,128}$/', $token)) {
                session_id($token);
                session_start();
            } elseif (!empty($_COOKIE[session_name()])) {
                session_start();
            } else {
                return;
            }
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Devuelve el ID del usuario autenticado en la sesión actual.
     * Útil en los controladores para saber quién está operando.
     *
     * @return int
     */
    public static function obtenerUsuarioId(): int
    {
        return (int) ($_SESSION['usuario_id'] ?? 0);
    }

    private static function obtenerBearerToken(): ?string
    {
        $headerAuth = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ($headerAuth === null && function_exists('getallheaders')) {
            foreach (getallheaders() as $nombre => $valor) {
                if (strcasecmp($nombre, 'Authorization') === 0) {
                    $headerAuth = $valor;
                    break;
                }
            }
        }

        if (!is_string($headerAuth) || !preg_match('/^Bearer\s+(.+)$/i', trim($headerAuth), $coincidencias)) {
            return null;
        }

        return trim($coincidencias[1]);
    }

    // ─────────────────────────────────────────────────────────
    // Helper privado: cortar ejecución con error JSON
    // ─────────────────────────────────────────────────────────

    /**
     * Envía una respuesta de error y termina la ejecución.
     * Ningún controlador protegido llega a ejecutarse si esto se dispara.
     */
    private static function denegar(int $codigo, string $mensaje): void
    {
        http_response_code($codigo);
        echo json_encode([
            'error'   => true,
            'mensaje' => $mensaje
        ], JSON_UNESCAPED_UNICODE);
        exit; // Cortamos todo — el controlador no se ejecuta
    }
}
