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

    public function listarTodos(?int $demoDatasetId = null): array
    {
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
        $sql .= ' ORDER BY m.fecha_vencimiento ASC, m.creado_en DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listarTodas(?int $demoDatasetId = null): array
    {
        return $this->listarTodos($demoDatasetId);
    }

    public function crear(int $usuarioId, string $plan, string $fechaInicio, string $fechaVencimiento, float $precioPagado): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO membresias (usuario_id, plan, fecha_inicio, fecha_vencimiento, precio_pagado)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->execute([$usuarioId, $plan, $fechaInicio, $fechaVencimiento, $precioPagado]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listarVencidas(?int $demoDatasetId = null): array
    {
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
        $sql .= ' ORDER BY m.fecha_vencimiento ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function contarPorVencer(int $dias = 7, ?int $demoDatasetId = null): int
    {
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
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, plan, fecha_inicio, fecha_vencimiento, estado, precio_pagado, creado_en
             FROM membresias
             WHERE usuario_id = ?
             ORDER BY fecha_vencimiento DESC'
        );

        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public function actualizarVencidas(?int $demoDatasetId = null): int
    {
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
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function tieneMembresiaActiva(int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM membresias
             WHERE usuario_id = ? AND estado = "activa"
               AND fecha_inicio <= CURRENT_DATE()
               AND fecha_vencimiento >= CURRENT_DATE()
             LIMIT 1'
        );
        $stmt->execute([$usuarioId]);

        return (bool) $stmt->fetchColumn();
    }

    public function obtenerPorUsuario(int $usuarioId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, usuario_id, plan, fecha_inicio, fecha_vencimiento, estado, precio_pagado, creado_en
             FROM membresias
             WHERE usuario_id = ?
             ORDER BY (estado = "activa") DESC, fecha_vencimiento DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetch();
    }

    public function activar(int $usuarioId, string $plan, float $precio): int
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
            $precio
        );
    }

    public function suspender(int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE membresias
             SET estado = "suspendida"
             WHERE usuario_id = ? AND estado = "activa"'
        );
        $stmt->execute([$usuarioId]);

        return $stmt->rowCount() > 0;
    }
}
