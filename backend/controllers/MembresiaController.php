<?php
/**
 * GymTrack · MembresiaController.php
 * ---------------------------------------------------------------
 * Maneja la gestión de membresías (RF-06 a RF-10).
 *
 * Endpoints:
 *   GET  /api/membresia/mia        → mia()      Membresía del socio autenticado
 *   POST /api/membresia/activar    → activar()  Admin: activar/renovar membresía
 *   POST /api/membresia/suspender  → suspender() Admin: suspender membresía
 *   GET  /api/membresia/todas      → todas()    Admin: listar todas
 */
class MembresiaController
{
    private Membresia $membresiaModel;

    public function __construct()
    {
        $this->membresiaModel = new Membresia();
    }

    /**
     * Devuelve la membresía del socio autenticado (RF-06).
     * También actualiza automáticamente membresías vencidas.
     */
    public function mia(): void
    {
        AuthMiddleware::verificarSesion();
        $usuarioId = AuthMiddleware::obtenerUsuarioId();

        // Actualizar vencidas antes de devolver el estado
        $this->membresiaModel->actualizarVencidas();

        $membresia = $this->membresiaModel->obtenerPorUsuario($usuarioId);

        if (!$membresia) {
            $this->responder(200, [
                'error'     => false,
                'membresia' => null,
                'estado'    => 'sin_membresia',
            ]);
            return;
        }

        // Calcular días restantes
        $hoy       = new DateTime();
        $venc      = new DateTime($membresia['fecha_vencimiento']);
        $diasRest  = (int) $hoy->diff($venc)->format('%r%a');

        $this->responder(200, [
            'error'      => false,
            'membresia'  => $membresia,
            'dias_restantes' => $diasRest,
            'por_vencer' => $diasRest <= 7 && $diasRest >= 0,
        ]);
    }

    /**
     * Activa o renueva la membresía de un socio (RF-07, RF-08).
     * Solo administradores.
     */
    public function activar(): void
    {
        AuthMiddleware::verificarSesion();
        AuthMiddleware::verificarRol(2);

        $datos = json_decode(file_get_contents('php://input'), true);

        if (!is_array($datos) || empty($datos['usuario_id']) || empty($datos['plan']) || !isset($datos['precio'])) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'Los campos usuario_id, plan y precio son obligatorios.',
            ]);
            return;
        }

        $planesValidos = ['mensual', 'trimestral', 'anual'];
        if (!in_array($datos['plan'], $planesValidos, true)) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'Plan inválido. Opciones: mensual, trimestral, anual.',
            ]);
            return;
        }

        if (!is_numeric($datos['precio']) || (float) $datos['precio'] < 0) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El precio no es válido.']);
            return;
        }

        $membresiaId = $this->membresiaModel->activar(
            (int) $datos['usuario_id'],
            $datos['plan'],
            (float) $datos['precio']
        );

        // Generar notificación para el socio
        $this->generarNotificacion(
            (int) $datos['usuario_id'],
            "Tu membresía {$datos['plan']} fue activada exitosamente."
        );

        $this->responder(201, [
            'error'        => false,
            'mensaje'      => "Membresía {$datos['plan']} activada correctamente.",
            'membresia_id' => $membresiaId,
        ]);
    }

    /**
     * Suspende la membresía de un socio (RF-07).
     * Solo administradores.
     */
    public function suspender(): void
    {
        AuthMiddleware::verificarSesion();
        AuthMiddleware::verificarRol(2);

        $datos = json_decode(file_get_contents('php://input'), true);

        if (!is_array($datos) || empty($datos['usuario_id'])) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El campo usuario_id es obligatorio.']);
            return;
        }

        $ok = $this->membresiaModel->suspender((int) $datos['usuario_id']);

        if ($ok) {
            $this->generarNotificacion(
                (int) $datos['usuario_id'],
                'Tu membresía fue suspendida. Contactá a la administración para más información.'
            );
            $this->responder(200, ['error' => false, 'mensaje' => 'Membresía suspendida correctamente.']);
        } else {
            $this->responder(400, ['error' => true, 'mensaje' => 'No se encontró membresía activa para suspender.']);
        }
    }

    /**
     * Lista todas las membresías para el admin.
     */
    public function todas(): void
    {
        AuthMiddleware::verificarSesion();
        AuthMiddleware::verificarRol(2);

        $this->membresiaModel->actualizarVencidas();
        $membresias = $this->membresiaModel->listarTodas();
        $this->responder(200, ['error' => false, 'membresias' => $membresias]);
    }

    /**
     * Genera una notificación interna para el socio (RF-09).
     */
    private function generarNotificacion(int $usuarioId, string $mensaje): void
    {
        try {
            $pdo = Database::conectar();
            $pdo->prepare(
                'INSERT INTO notificaciones (usuario_id, mensaje, leida) VALUES (?, ?, 0)'
            )->execute([$usuarioId, $mensaje]);
        } catch (Exception $e) {
            // Las notificaciones no deben interrumpir el flujo principal
        }
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
