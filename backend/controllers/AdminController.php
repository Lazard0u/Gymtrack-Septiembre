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
        $socios = $this->usuarioModel->listarSocios($this->demoDatasetId(), AuthMiddleware::obtenerGimnasioContextoId());

        $this->responder(200, [
            'error'  => false,
            'socios' => $socios
        ]);
    }

    public function verSocio(int $id): void
    {
        $this->verificarAdmin();

        $socio = $this->usuarioModel->buscarPorIdEnDataset($id, $this->demoDatasetId(), AuthMiddleware::obtenerGimnasioContextoId());
        if (!$socio || $socio['rol_nombre'] !== AuthorizationService::SOCIO) {
            $this->responder(404, [
                'error'   => true,
                'mensaje' => 'No se encontró ese socio.'
            ]);
            return;
        }

        $membresias = $this->membresiaModel->listarPorUsuario($id, AuthMiddleware::obtenerGimnasioContextoId());

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
        $gimnasioId = AuthMiddleware::requerirContextoGimnasio();
        $resultado = $this->usuarioModel->cambiarEstado($id, $activo, $gimnasioId, $this->demoDatasetId());

        if ($resultado) {
            AdminAuditLogger::record(
                'member.legacy_status.changed',
                'usuario',
                'success',
                AuthMiddleware::obtenerUsuarioId(),
                $gimnasioId,
                (string) $id,
                'Contrato heredado fuera del router',
                null,
                ['activo' => $activo]
            );
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
        $membresias = $this->membresiaModel->listarTodos($this->demoDatasetId(), AuthMiddleware::obtenerGimnasioContextoId());

        $this->responder(200, [
            'error'      => false,
            'membresias' => $membresias
        ]);
    }

    public function crearMembresia(): void
    {
        $this->verificarAdmin();
        AuthMiddleware::impedirMutacionDemo();
        $gimnasioId = AuthMiddleware::requerirContextoGimnasio();

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

        try {
            $id = $this->membresiaModel->crear(
                (int) $datos['usuario_id'],
                $datos['plan'],
                $datos['fecha_inicio'],
                $datos['fecha_vencimiento'],
                (float) $datos['precio_pagado'],
                $gimnasioId
            );
        } catch (DomainException $e) {
            $this->responder(404, ['error' => true, 'mensaje' => $e->getMessage()]);
            return;
        }

        $this->responder(201, [
            'error'      => false,
            'mensaje'    => 'Membresía creada correctamente.',
            'membresia'  => ['id' => $id]
        ]);
    }

    public function listarMembresiasVencidas(): void
    {
        $this->verificarAdmin();
        $membresias = $this->membresiaModel->listarVencidas($this->demoDatasetId(), AuthMiddleware::obtenerGimnasioContextoId());

        $this->responder(200, [
            'error'      => false,
            'membresias' => $membresias
        ]);
    }

    public function listarClases(): void
    {
        $this->verificarAdmin();
        $clases = $this->claseModel->listarTodos($this->demoDatasetId(), AuthMiddleware::obtenerGimnasioContextoId());

        $this->responder(200, [
            'error'   => false,
            'clases'  => $clases
        ]);
    }

    public function crearClase(): void
    {
        $this->verificarAdmin();
        AuthMiddleware::impedirMutacionDemo();
        $gimnasioId = AuthMiddleware::requerirContextoGimnasio();

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

        try {
            $id = $this->claseModel->crear(
                htmlspecialchars(trim($datos['nombre']), ENT_QUOTES, 'UTF-8'),
                (int) $datos['instructor_id'],
                $datos['dia_semana'],
                $datos['hora_inicio'],
                $datos['hora_fin'],
                (int) $datos['cupo_maximo'],
                $gimnasioId
            );
        } catch (DomainException $e) {
            $this->responder(404, ['error' => true, 'mensaje' => $e->getMessage()]);
            return;
        }

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
        $gimnasioId = AuthMiddleware::requerirContextoGimnasio();

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
            (int) $datos['cupo_maximo'],
            1,
            $gimnasioId
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
        $gimnasioId = AuthMiddleware::requerirContextoGimnasio();

        $resultado = $this->claseModel->cancelar($id, $gimnasioId);

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
        $gimnasioId = AuthMiddleware::obtenerGimnasioContextoId();
        if (!$this->claseModel->buscarPorId($claseId, $this->demoDatasetId(), $gimnasioId)) {
            $this->responder(404, ['error' => true, 'mensaje' => 'No se encontró esa clase en este dataset.']);
            return;
        }
        $inscriptos = $this->claseModel->listarInscriptos($claseId, $gimnasioId);

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
            $this->demoDatasetId(),
            AuthMiddleware::obtenerGimnasioContextoId()
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
        $gimnasioId = AuthMiddleware::requerirContextoGimnasio();

        $resultado = $this->reservaModel->cancelar($id, null, $gimnasioId);

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
        $gimnasioId = AuthMiddleware::obtenerGimnasioContextoId();
        $sociosActivos = $this->usuarioModel->contarSociosActivos($datasetId, $gimnasioId);
        $clasesHoy     = $this->claseModel->contarClasesHoy($datasetId, $gimnasioId);
        $porVencer     = $this->membresiaModel->contarPorVencer(7, $datasetId, $gimnasioId);

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
        AuthMiddleware::verificarRol(AuthorizationService::ADMIN);
        AuthMiddleware::requerirContextoGimnasio();
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
