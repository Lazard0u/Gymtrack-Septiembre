<?php
/**
 * GymTrack · PerfilController.php (Controller)
 * ---------------------------------------------------------------
 * Maneja las peticiones relacionadas con el perfil del usuario
 * autenticado: ver y actualizar sus datos personales (RF-05).
 *
 * Endpoints:
 *   GET /api/perfil  → ver()        Ver perfil del usuario autenticado
 *   PUT /api/perfil  → actualizar() Actualizar nombre y teléfono
 *
 * Ambas rutas están protegidas — requieren sesión activa.
 */

class PerfilController
{
    private Usuario $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new Usuario();
    }

    // ─────────────────────────────────────────────────────────
    // VER PERFIL  →  GET /api/perfil
    // ─────────────────────────────────────────────────────────

    /**
     * Devuelve los datos del usuario autenticado.
     * El frontend (dashboard Vue) usa esta ruta para mostrar
     * el saludo personalizado y los datos del perfil.
     */
    public function ver(): void
    {
        // El middleware verifica el token antes de llegar acá.
        // Si no está autenticado, el middleware corta con 401.
        AuthMiddleware::verificarSesion();

        // Obtenemos el ID del usuario de la sesión activa
        $id = AuthMiddleware::obtenerUsuarioId();

        $usuario = $this->usuarioModel->buscarPorId($id);

        if (!$usuario) {
            $this->responder(404, [
                'error'   => true,
                'mensaje' => 'Usuario no encontrado.'
            ]);
            return;
        }

        // Sanitizamos la salida antes de enviarla al frontend (anti-XSS)
        $this->responder(200, [
            'error'   => false,
            'usuario' => [
                'id'        => $usuario['id'],
                'nombre'    => htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8'),
                'email'     => $usuario['email'],
                'telefono'  => $usuario['telefono']
                    ? htmlspecialchars($usuario['telefono'], ENT_QUOTES, 'UTF-8')
                    : null,
                'rol_id'    => $usuario['rol_id'],
                'creado_en' => $usuario['creado_en'],
            ]
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ACTUALIZAR PERFIL  →  PUT /api/perfil
    // ─────────────────────────────────────────────────────────

    /**
     * Actualiza nombre y teléfono del usuario autenticado (RF-05).
     * Solo puede editar su propio perfil — no el de otros.
     */
    public function actualizar(): void
    {
        AuthMiddleware::verificarSesion();

        $id    = AuthMiddleware::obtenerUsuarioId();
        $datos = json_decode(file_get_contents('php://input'), true);

        if (empty($datos['nombre'])) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'El nombre es obligatorio.'
            ]);
            return;
        }

        $nombre   = htmlspecialchars(trim($datos['nombre']), ENT_QUOTES, 'UTF-8');
        $telefono = isset($datos['telefono'])
            ? htmlspecialchars(trim($datos['telefono']), ENT_QUOTES, 'UTF-8')
            : null;

        $actualizado = $this->usuarioModel->actualizarPerfil($id, $nombre, $telefono);

        if ($actualizado) {
            $this->responder(200, [
                'error'   => false,
                'mensaje' => 'Perfil actualizado correctamente.'
            ]);
        } else {
            $this->responder(500, [
                'error'   => true,
                'mensaje' => 'No se pudo actualizar el perfil.'
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
