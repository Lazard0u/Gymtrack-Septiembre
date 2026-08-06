<?php
/**
 * GymTrack · AdminController.php
 * ---------------------------------------------------------------
 * Controlador para las operaciones de administración.
 * Solo usuarios con rol admin pueden acceder a estas rutas.
 */

class AdminController
{
    private Usuario $usuarioModel;
    private Membresia $membresiaModel;
    private Clase $claseModel;
    private Reserva $reservaModel;

    public function __construct()
    {
        $this->usuarioModel   = new Usuario();
        $this->membresiaModel = new Membresia();
        $this->claseModel     = new Clase();
        $this->reservaModel   = new Reserva();
    }

    public function listarSocios(): void
    {
        $this->verificarAdmin();
        $socios = $this->usuarioModel->listarSocios($this->demoDatasetId());

        $this->responder(200, [
            'error'  => false,
            'socios' => $socios
        ]);
    }

    public function verSocio(int $id): void
    {
        $this->verificarAdmin();

        $socio = $this->usuarioModel->buscarPorIdEnDataset($id, $this->demoDatasetId());
        if (!$socio || (int) $socio['rol_id'] !== 1) {
            $this->responder(404, [
                'error'   => true,
                'mensaje' => 'No se encontró ese socio.'
            ]);
            return;
        }

        $membresias = $this->membresiaModel->listarPorUsuario($id);

        $this->responder(200, [
            'error'      => false,
            'socio'      => $socio,
            'membresias' => $membresias
        ]);
    }

    public function actualizarEstadoSocio(int $id): void
    {
        $this->verificarAdmin();
        AuthMiddleware::impedirMutacionDemo();

        $datos = json_decode(file_get_contents('php://input'), true);
        if (!isset($datos['activo'])) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'El estado activo es obligatorio.'
            ]);
            return;
        }

        $activo = $datos['activo'] ? 1 : 0;
        $resultado = $this->usuarioModel->cambiarEstado($id, $activo);

        if ($resultado) {
            $this->responder(200, [
                'error'   => false,
                'mensaje' => 'Estado de la cuenta actualizado.'
            ]);
        } else {
            $this->responder(500, [
                'error'   => true,
                'mensaje' => 'No se pudo actualizar el estado de la cuenta.'
            ]);
        }
    }

    public function listarMembresias(): void
    {
        $this->verificarAdmin();
        $membresias = $this->membresiaModel->listarTodos($this->demoDatasetId());

        $this->responder(200, [
            'error'      => false,
            'membresias' => $membresias
        ]);
    }

    public function crearMembresia(): void
    {
        $this->verificarAdmin();
        AuthMiddleware::impedirMutacionDemo();

        $datos = json_decode(file_get_contents('php://input'), true);
        $campos = ['usuario_id', 'plan', 'fecha_inicio', 'fecha_vencimiento', 'precio_pagado'];
        foreach ($campos as $campo) {
            if (empty($datos[$campo])) {
                $this->responder(400, [
                    'error'   => true,
                    'mensaje' => "El campo '{$campo}' es obligatorio."
                ]);
                return;
            }
        }

        $planesValidos = ['mensual', 'trimestral', 'anual'];
        if (!in_array($datos['plan'], $planesValidos, true)) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'Plan de membresía inválido.'
            ]);
            return;
        }

        $usuario = $this->usuarioModel->buscarPorId((int) $datos['usuario_id']);
        if (!$usuario) {
            $this->responder(404, [
                'error'   => true,
                'mensaje' => 'El socio no existe.'
            ]);
            return;
        }

        $id = $this->membresiaModel->crear(
            (int) $datos['usuario_id'],
            $datos['plan'],
            $datos['fecha_inicio'],
            $datos['fecha_vencimiento'],
            (float) $datos['precio_pagado']
        );

        $this->responder(201, [
            'error'      => false,
            'mensaje'    => 'Membresía creada correctamente.',
            'membresia'  => ['id' => $id]
        ]);
    }

    public function listarMembresiasVencidas(): void
    {
        $this->verificarAdmin();
        $membresias = $this->membresiaModel->listarVencidas($this->demoDatasetId());

        $this->responder(200, [
            'error'      => false,
            'membresias' => $membresias
        ]);
    }

    public function listarClases(): void
    {
        $this->verificarAdmin();
        $clases = $this->claseModel->listarTodos($this->demoDatasetId());

        $this->responder(200, [
            'error'   => false,
            'clases'  => $clases
        ]);
    }

    public function crearClase(): void
    {
        $this->verificarAdmin();
        AuthMiddleware::impedirMutacionDemo();

        $datos = json_decode(file_get_contents('php://input'), true);
        $campos = ['nombre', 'instructor_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'cupo_maximo'];
        foreach ($campos as $campo) {
            if (empty($datos[$campo])) {
                $this->responder(400, [
                    'error'   => true,
                    'mensaje' => "El campo '{$campo}' es obligatorio."
                ]);
                return;
            }
        }

        $diasValidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        if (!in_array($datos['dia_semana'], $diasValidos, true)) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'Día de la semana inválido.'
            ]);
            return;
        }

        $instructor = $this->usuarioModel->buscarPorId((int) $datos['instructor_id']);
        if (!$instructor) {
            $this->responder(404, [
                'error'   => true,
                'mensaje' => 'El instructor no existe.'
            ]);
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
            'error'  => false,
            'mensaje'=> 'Clase creada correctamente.',
            'clase'  => ['id' => $id]
        ]);
    }

    public function actualizarClase(int $id): void
    {
        $this->verificarAdmin();
        AuthMiddleware::impedirMutacionDemo();

        $datos = json_decode(file_get_contents('php://input'), true);
        $campos = ['nombre', 'instructor_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'cupo_maximo'];
        foreach ($campos as $campo) {
            if (empty($datos[$campo])) {
                $this->responder(400, [
                    'error'   => true,
                    'mensaje' => "El campo '{$campo}' es obligatorio."
                ]);
                return;
            }
        }

        $diasValidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        if (!in_array($datos['dia_semana'], $diasValidos, true)) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'Día de la semana inválido.'
            ]);
            return;
        }

        $resultado = $this->claseModel->actualizar(
            $id,
            htmlspecialchars(trim($datos['nombre']), ENT_QUOTES, 'UTF-8'),
            (int) $datos['instructor_id'],
            $datos['dia_semana'],
            $datos['hora_inicio'],
            $datos['hora_fin'],
            (int) $datos['cupo_maximo']
        );

        if ($resultado) {
            $this->responder(200, [
                'error'   => false,
                'mensaje' => 'Clase actualizada correctamente.'
            ]);
        } else {
            $this->responder(500, [
                'error'   => true,
                'mensaje' => 'No se pudo actualizar la clase.'
            ]);
        }
    }

    public function cancelarClase(int $id): void
    {
        $this->verificarAdmin();
        AuthMiddleware::impedirMutacionDemo();

        $resultado = $this->claseModel->cancelar($id);

        if ($resultado) {
            $this->responder(200, [
                'error'   => false,
                'mensaje' => 'Clase cancelada correctamente.'
            ]);
        } else {
            $this->responder(500, [
                'error'   => true,
                'mensaje' => 'No se pudo cancelar la clase.'
            ]);
        }
    }

    public function listarInscriptosClase(int $claseId): void
    {
        $this->verificarAdmin();
        if (!$this->claseModel->buscarPorId($claseId, $this->demoDatasetId())) {
            $this->responder(404, ['error' => true, 'mensaje' => 'No se encontró esa clase en este dataset.']);
            return;
        }
        $inscriptos = $this->claseModel->listarInscriptos($claseId);

        $this->responder(200, [
            'error'     => false,
            'inscriptos'=> $inscriptos
        ]);
    }

    public function listarReservas(): void
    {
        $this->verificarAdmin();

        $period = $_GET['period'] ?? 'day';
        $desde  = new DateTime();
        $hasta  = new DateTime();

        if ($period === 'week') {
            $hasta->modify('+7 days');
        } else {
            $hasta->modify('+1 day');
        }

        $reservas = $this->reservaModel->listarPorRango(
            $desde->format('Y-m-d 00:00:00'),
            $hasta->format('Y-m-d 23:59:59'),
            $this->demoDatasetId()
        );

        $this->responder(200, [
            'error'   => false,
            'reservas'=> $reservas
        ]);
    }

    public function cancelarReserva(int $id): void
    {
        $this->verificarAdmin();
        AuthMiddleware::impedirMutacionDemo();

        $resultado = $this->reservaModel->cancelar($id);

        if ($resultado['ok']) {
            $this->responder(200, [
                'error'   => false,
                'mensaje' => $resultado['mensaje']
            ]);
        } else {
            $this->responder(404, [
                'error'   => true,
                'mensaje' => $resultado['mensaje']
            ]);
        }
    }

    public function estadisticas(): void
    {
        $this->verificarAdmin();

        $datasetId = $this->demoDatasetId();
        $sociosActivos = $this->usuarioModel->contarSociosActivos($datasetId);
        $clasesHoy     = $this->claseModel->contarClasesHoy($datasetId);
        $porVencer     = $this->membresiaModel->contarPorVencer(7, $datasetId);

        $this->responder(200, [
            'error'   => false,
            'datos'   => [
                'socios_activos'          => $sociosActivos,
                'clases_hoy'              => $clasesHoy,
                'socios_membresias_por_vencer' => $porVencer,
            ]
        ]);
    }

    private function verificarAdmin(): void
    {
        AuthMiddleware::verificarSesion();
        AuthMiddleware::verificarRol(2);
    }

    private function demoDatasetId(): ?int
    {
        return AuthMiddleware::obtenerDemoDatasetId();
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
