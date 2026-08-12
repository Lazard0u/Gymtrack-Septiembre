<?php
/**
 * GymTrack · Membresia.php
 * ---------------------------------------------------------------
 * Modelo para la tabla membresias.
 */

class Membresia
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    public function listarTodos(?int $demoDatasetId = null, ?int $gimnasioId = null): array
    {
        $this->requireGym($gimnasioId);
        $sql =
            'SELECT m.id, m.usuario_id, u.nombre AS usuario_nombre, u.email AS usuario_email,
                    m.plan, m.fecha_inicio, m.fecha_vencimiento, m.estado, m.precio_pagado, m.creado_en
             FROM membresias m
             JOIN usuarios u ON m.usuario_id = u.id
             WHERE m.is_demo = ?';
        $params = [$demoDatasetId === null ? 0 : 1];
        if ($demoDatasetId !== null) {
            $sql .= ' AND m.demo_dataset_id = ?';
            $params[] = $demoDatasetId;
        }
        if ($gimnasioId !== null) {
            $sql .= ' AND m.gimnasio_id = ?';
            $params[] = $gimnasioId;
        }
        $sql .= ' ORDER BY m.fecha_vencimiento ASC, m.creado_en DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listarTodas(?int $demoDatasetId = null, ?int $gimnasioId = null): array
    {
        return $this->listarTodos($demoDatasetId, $gimnasioId);
    }

    public function crear(int $usuarioId, string $plan, string $fechaInicio, string $fechaVencimiento, float $precioPagado, int $gimnasioId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO membresias (usuario_id, gimnasio_id, plan, fecha_inicio, fecha_vencimiento, precio_pagado)
             SELECT ?, ?, ?, ?, ?, ?
             WHERE EXISTS (
                 SELECT 1 FROM usuario_gimnasio_roles
                 WHERE usuario_id = ? AND gimnasio_id = ? AND activo = 1
             )'
        );

        $stmt->execute([$usuarioId, $gimnasioId, $plan, $fechaInicio, $fechaVencimiento, $precioPagado, $usuarioId, $gimnasioId]);
        if ($stmt->rowCount() !== 1) {
            throw new DomainException('El socio no pertenece al gimnasio activo.');
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function listarVencidas(?int $demoDatasetId = null, ?int $gimnasioId = null): array
    {
        $this->requireGym($gimnasioId);
        $sql =
            'SELECT m.id, m.usuario_id, u.nombre AS usuario_nombre, u.email AS usuario_email,
                    m.plan, m.fecha_inicio, m.fecha_vencimiento, m.estado, m.precio_pagado
             FROM membresias m
             JOIN usuarios u ON m.usuario_id = u.id
             WHERE (m.estado = "vencida" OR m.fecha_vencimiento < CURRENT_DATE())
               AND m.is_demo = ?';
        $params = [$demoDatasetId === null ? 0 : 1];
        if ($demoDatasetId !== null) {
            $sql .= ' AND m.demo_dataset_id = ?';
            $params[] = $demoDatasetId;
        }
        if ($gimnasioId !== null) {
            $sql .= ' AND m.gimnasio_id = ?';
            $params[] = $gimnasioId;
        }
        $sql .= ' ORDER BY m.fecha_vencimiento ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function contarPorVencer(int $dias = 7, ?int $demoDatasetId = null, ?int $gimnasioId = null): int
    {
        $this->requireGym($gimnasioId);
        $sql =
            'SELECT COUNT(DISTINCT m.usuario_id)
             FROM membresias m
             WHERE m.estado = "activa"
               AND m.fecha_vencimiento BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL ? DAY)
               AND m.is_demo = ?';
        $params = [$dias, $demoDatasetId === null ? 0 : 1];
        if ($demoDatasetId !== null) {
            $sql .= ' AND m.demo_dataset_id = ?';
            $params[] = $demoDatasetId;
        }
        if ($gimnasioId !== null) {
            $sql .= ' AND m.gimnasio_id = ?';
            $params[] = $gimnasioId;
        }
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function listarPorUsuario(int $usuarioId, ?int $gimnasioId = null): array
    {
        $this->requireGym($gimnasioId);
        $stmt = $this->pdo->prepare(
            'SELECT id, plan, fecha_inicio, fecha_vencimiento, estado, precio_pagado, creado_en
             FROM membresias
             WHERE usuario_id = ? AND (? IS NULL OR gimnasio_id = ?)
             ORDER BY fecha_vencimiento DESC'
        );

        $stmt->execute([$usuarioId, $gimnasioId, $gimnasioId]);

        return $stmt->fetchAll();
    }

    public function actualizarVencidas(?int $demoDatasetId = null, ?int $gimnasioId = null): int
    {
        $this->requireGym($gimnasioId);
        $sql =
            'UPDATE membresias
             SET estado = "vencida"
             WHERE estado = "activa" AND fecha_vencimiento < CURRENT_DATE()
               AND is_demo = ?';
        $params = [$demoDatasetId === null ? 0 : 1];
        if ($demoDatasetId !== null) {
            $sql .= ' AND demo_dataset_id = ?';
            $params[] = $demoDatasetId;
        }
        if ($gimnasioId !== null) {
            $sql .= ' AND gimnasio_id = ?';
            $params[] = $gimnasioId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function tieneMembresiaActiva(int $usuarioId, ?int $gimnasioId = null): bool
    {
        $this->requireGym($gimnasioId);
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM membresias
             WHERE usuario_id = ? AND estado = "activa"
               AND (? IS NULL OR gimnasio_id = ?)
               AND fecha_inicio <= CURRENT_DATE()
               AND fecha_vencimiento >= CURRENT_DATE()
             LIMIT 1'
        );
        $stmt->execute([$usuarioId, $gimnasioId, $gimnasioId]);

        return (bool) $stmt->fetchColumn();
    }

    public function obtenerPorUsuario(int $usuarioId, ?int $gimnasioId = null): array|false
    {
        $this->requireGym($gimnasioId);
        $stmt = $this->pdo->prepare(
            'SELECT id, usuario_id, plan, fecha_inicio, fecha_vencimiento, estado, precio_pagado, creado_en
             FROM membresias
             WHERE usuario_id = ?
               AND (? IS NULL OR gimnasio_id = ?)
             ORDER BY (estado = "activa") DESC, fecha_vencimiento DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$usuarioId, $gimnasioId, $gimnasioId]);

        return $stmt->fetch();
    }

    public function activar(int $usuarioId, string $plan, float $precio, int $gimnasioId): int
    {
        $duraciones = ['mensual' => '+1 month', 'trimestral' => '+3 months', 'anual' => '+1 year'];
        if (!isset($duraciones[$plan])) {
            throw new InvalidArgumentException('Plan de membresía inválido.');
        }

        $inicio = new DateTimeImmutable('today');
        $vencimiento = $inicio->modify($duraciones[$plan]);

        return $this->crear(
            $usuarioId,
            $plan,
            $inicio->format('Y-m-d'),
            $vencimiento->format('Y-m-d'),
            $precio,
            $gimnasioId
        );
    }

    public function suspender(int $usuarioId, int $gimnasioId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE membresias
             SET estado = "suspendida"
             WHERE usuario_id = ? AND gimnasio_id = ? AND estado = "activa"'
        );
        $stmt->execute([$usuarioId, $gimnasioId]);

        return $stmt->rowCount() > 0;
    }

    private function requireGym(?int $gimnasioId): void
    {
        if ($gimnasioId === null || $gimnasioId < 1) {
            throw new LogicException('Membresía requiere un contexto de gimnasio explícito.');
        }
    }
}
