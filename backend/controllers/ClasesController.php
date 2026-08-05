<?php
/**
 * GymTrack · ClasesController.php
 * ---------------------------------------------------------------
 * Maneja el CRUD completo de clases (RF-11, RF-16).
 *
 * Endpoints:
 *   GET    /api/clases          → listar()    Público autenticado
 *   GET    /api/clases/todas    → todas()     Solo admin
 *   GET    /api/clases/{id}     → ver()       Autenticado
 *   POST   /api/clases          → crear()     Solo admin
 *   PUT    /api/clases/{id}     → actualizar() Solo admin
 *   DELETE /api/clases/{id}     → eliminar()  Solo admin
 *   GET    /api/clases/stats    → stats()     Solo admin
 */
class ClasesController
{
    private Clase $claseModel;

    public function __construct()
    {
        $this->claseModel = new Clase();
    }

    /**
     * Lista todas las clases activas con instructor y cupos (RF-11).
     * Accesible para cualquier socio autenticado.
     */
    public function listar(): void
    {
        AuthMiddleware::verificarSesion();
        $clases = $this->claseModel->listarActivas();
        $this->responder(200, ['error' => false, 'clases' => $clases]);
    }

    /**
     * Lista TODAS las clases (incluidas inactivas) para el admin.
     */
    public function todas(): void
    {
        AuthMiddleware::verificarSesion();
        AuthMiddleware::verificarRol(2); // Solo admin
        $clases = $this->claseModel->listarTodas();
        $this->responder(200, ['error' => false, 'clases' => $clases]);
    }

    /**
     * Devuelve los datos de una clase por ID.
     */
    public function ver(int $id): void
    {
        AuthMiddleware::verificarSesion();
        $clase = $this->claseModel->buscarPorId($id);
        if (!$clase) {
            $this->responder(404, ['error' => true, 'mensaje' => 'Clase no encontrada.']);
            return;
        }
        $this->responder(200, ['error' => false, 'clase' => $clase]);
    }

    /**
     * Crea una nueva clase (RF-16). Solo admins.
     */
    public function crear(): void
    {
        AuthMiddleware::verificarSesion();
        AuthMiddleware::verificarRol(2);

        $datos = json_decode(file_get_contents('php://input'), true);
        if (!is_array($datos)) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El cuerpo de la petición no es válido.']);
            return;
        }

        $requeridos = ['nombre', 'instructor_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'cupo_maximo'];
        foreach ($requeridos as $campo) {
            if (empty($datos[$campo])) {
                $this->responder(400, ['error' => true, 'mensaje' => "El campo '{$campo}' es obligatorio."]);
                return;
            }
        }

        if (!$this->datosClaseValidos($datos)) {
            return;
        }

        $id = $this->claseModel->crear(
            htmlspecialchars(trim($datos['nombre']), ENT_QUOTES, 'UTF-8'),
            (int) $datos['instructor_id'],
            $datos['dia_semana'],
            $datos['hora_inicio'],
            $datos['hora_fin'],
            (int) $datos['cupo_maximo']
        );

        $this->responder(201, [
            'error'   => false,
            'mensaje' => 'Clase creada exitosamente.',
            'id'      => $id,
        ]);
    }

    /**
     * Actualiza una clase existente (RF-16). Solo admins.
     */
    public function actualizar(int $id): void
    {
        AuthMiddleware::verificarSesion();
        AuthMiddleware::verificarRol(2);

        $datos = json_decode(file_get_contents('php://input'), true);
        if (!is_array($datos)) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El cuerpo de la petición no es válido.']);
            return;
        }

        $requeridos = ['nombre', 'instructor_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'cupo_maximo'];
        foreach ($requeridos as $campo) {
            if (!isset($datos[$campo]) || $datos[$campo] === '') {
                $this->responder(400, ['error' => true, 'mensaje' => "El campo '{$campo}' es obligatorio."]);
                return;
            }
        }

        if (!$this->datosClaseValidos($datos)) {
            return;
        }

        $ok = $this->claseModel->actualizar(
            $id,
            htmlspecialchars(trim($datos['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'),
            (int) ($datos['instructor_id'] ?? 0),
            $datos['dia_semana'] ?? '',
            $datos['hora_inicio'] ?? '',
            $datos['hora_fin'] ?? '',
            (int) ($datos['cupo_maximo'] ?? 0),
            (int) ($datos['activa'] ?? 1)
        );

        if ($ok) {
            $this->responder(200, ['error' => false, 'mensaje' => 'Clase actualizada correctamente.']);
        } else {
            $this->responder(500, ['error' => true, 'mensaje' => 'No se pudo actualizar la clase.']);
        }
    }

    /**
     * Elimina (desactiva) una clase (RF-16). Solo admins.
     */
    public function eliminar(int $id): void
    {
        AuthMiddleware::verificarSesion();
        AuthMiddleware::verificarRol(2);

        $ok = $this->claseModel->eliminar($id);
        if ($ok) {
            $this->responder(200, ['error' => false, 'mensaje' => 'Clase eliminada correctamente.']);
        } else {
            $this->responder(500, ['error' => true, 'mensaje' => 'No se pudo eliminar la clase.']);
        }
    }

    /**
     * Estadísticas de ocupación (RF-20). Solo admins.
     */
    public function stats(): void
    {
        AuthMiddleware::verificarSesion();
        AuthMiddleware::verificarRol(2);

        $stats = $this->claseModel->estadisticas();
        $this->responder(200, ['error' => false, 'estadisticas' => $stats]);
    }

    private function datosClaseValidos(array $datos): bool
    {
        $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $cupo = (int) ($datos['cupo_maximo'] ?? 0);
        $inicio = (string) ($datos['hora_inicio'] ?? '');
        $fin = (string) ($datos['hora_fin'] ?? '');

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El nombre debe tener entre 2 y 100 caracteres.']);
            return false;
        }
        if (!in_array($datos['dia_semana'] ?? '', $dias, true)) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El día de la semana no es válido.']);
            return false;
        }
        if (!preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $inicio)
            || !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $fin)
            || $inicio >= $fin) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El horario de fin debe ser posterior al de inicio.']);
            return false;
        }
        if ($cupo < 1 || $cupo > 500) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El cupo debe estar entre 1 y 500.']);
            return false;
        }

        return true;
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
