<?php
/**
 * GymTrack · ReservasController.php
 * ---------------------------------------------------------------
 * Maneja las reservas de clases (RF-12, RF-13, RF-14, RF-15).
 *
 * Endpoints:
 *   POST   /api/reservas         → crear()   Crear reserva
 *   DELETE /api/reservas/{id}    → cancelar() Cancelar reserva
 *   GET    /api/reservas/mias    → mias()    Reservas del socio autenticado
 *   GET    /api/reservas/todas   → todas()   Todas las reservas (admin)
 */
class ReservasController
{
    private Reserva $reservaModel;
    private Membresia $membresiaModel;

    public function __construct()
    {
        $this->reservaModel   = new Reserva();
        $this->membresiaModel = new Membresia();
    }

    /**
     * Crea una reserva con control de cupos atómico (RF-12, RF-13).
     *
     * Antes de intentar la reserva:
     *  1. Verifica que el socio tenga membresía activa (RF-10)
     *  2. Llama al modelo que ejecuta la transacción atómica
     */
    public function crear(): void
    {
        AuthMiddleware::verificarPermiso('reservations.write');
        $usuarioId = AuthMiddleware::obtenerUsuarioId();
        $gimnasioId = AuthMiddleware::requerirContextoGimnasio();

        $datos = json_decode(file_get_contents('php://input'), true);

        if (!is_array($datos) || empty($datos['clase_id'])) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El campo clase_id es obligatorio.']);
            return;
        }

        // Actualizar estados vencidos antes de verificar
        $datasetId = AuthMiddleware::obtenerDemoDatasetId();
        $this->membresiaModel->actualizarVencidas($datasetId, $gimnasioId);

        // Verificar membresía activa (RF-10)
        if (!$this->membresiaModel->tieneMembresiaActiva($usuarioId, $gimnasioId)) {
            $this->responder(403, [
                'error'   => true,
                'mensaje' => 'Tu membresía está vencida o inactiva. Contactá a la administración para renovarla.',
            ]);
            return;
        }

        // Intentar la reserva con transacción atómica
        $resultado = $this->reservaModel->reservar($usuarioId, (int) $datos['clase_id'], $datasetId, $gimnasioId);

        if ($resultado['ok']) {
            $this->responder(201, [
                'error'      => false,
                'mensaje'    => $resultado['mensaje'],
                'reserva_id' => $resultado['reserva_id'],
            ]);
        } else {
            // 409 Conflict: sin cupos o reserva duplicada
            $this->responder(409, ['error' => true, 'mensaje' => $resultado['mensaje']]);
        }
    }

    /**
     * Cancela una reserva del socio autenticado (RF-14, RF-15).
     */
    public function cancelar(int $id): void
    {
        AuthMiddleware::verificarPermiso('reservations.write');
        $usuarioId = AuthMiddleware::obtenerUsuarioId();
        $gimnasioId = AuthMiddleware::requerirContextoGimnasio();

        $resultado = $this->reservaModel->cancelar($id, $usuarioId, $gimnasioId);

        if ($resultado['ok']) {
            $this->responder(200, ['error' => false, 'mensaje' => $resultado['mensaje']]);
        } else {
            $codigo = str_contains($resultado['mensaje'], 'no existe') ? 404 : 409;
            $this->responder($codigo, ['error' => true, 'mensaje' => $resultado['mensaje']]);
        }
    }

    /**
     * Lista las reservas del socio autenticado.
     */
    public function mias(): void
    {
        AuthMiddleware::verificarPermiso('reservations.read');
        $usuarioId = AuthMiddleware::obtenerUsuarioId();

        $reservas = $this->reservaModel->listarPorUsuario($usuarioId, AuthMiddleware::obtenerGimnasioContextoId());
        $this->responder(200, ['error' => false, 'reservas' => $reservas]);
    }

    /**
     * Lista todas las reservas para el admin (RF-18).
     * Acepta ?usuario_id=X para filtrar por socio.
     */
    public function todas(): void
    {
        AuthMiddleware::verificarPermiso('reservations.read');

        $usuarioId = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : null;
        $reservas  = $this->reservaModel->listarTodas($usuarioId, AuthMiddleware::obtenerDemoDatasetId(), AuthMiddleware::obtenerGimnasioContextoId());

        $this->responder(200, ['error' => false, 'reservas' => $reservas]);
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
