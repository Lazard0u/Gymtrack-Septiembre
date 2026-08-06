<?php
/**
 * GymTrack · Clase.php
 * ---------------------------------------------------------------
 * Modelo para la tabla clases.
 */

class Clase
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    public function listarTodos(?int $demoDatasetId = null): array
    {
        $sql =
            'SELECT c.id, c.nombre, c.instructor_id, u.nombre AS instructor_nombre,
                    c.dia_semana, c.hora_inicio, c.hora_fin, c.cupo_maximo,
                    c.cupos_disponibles, c.activa
             FROM clases c
             JOIN usuarios u ON c.instructor_id = u.id
             WHERE c.is_demo = ?';
        $params = [$demoDatasetId === null ? 0 : 1];
        if ($demoDatasetId !== null) {
            $sql .= ' AND c.demo_dataset_id = ?';
            $params[] = $demoDatasetId;
        }
        $sql .= ' ORDER BY FIELD(c.dia_semana, "lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"), c.hora_inicio';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listarTodas(?int $demoDatasetId = null): array
    {
        return $this->listarTodos($demoDatasetId);
    }

    public function listarActivas(?int $demoDatasetId = null): array
    {
        $sql =
            'SELECT c.id, c.nombre, c.instructor_id, u.nombre AS instructor_nombre,
                    c.dia_semana, c.hora_inicio, c.hora_fin, c.cupo_maximo,
                    c.cupos_disponibles, c.activa
             FROM clases c
             JOIN usuarios u ON c.instructor_id = u.id
             WHERE c.activa = 1 AND c.is_demo = ?';
        $params = [$demoDatasetId === null ? 0 : 1];
        if ($demoDatasetId !== null) {
            $sql .= ' AND c.demo_dataset_id = ?';
            $params[] = $demoDatasetId;
        }
        $sql .= ' ORDER BY FIELD(c.dia_semana, "lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"), c.hora_inicio';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function crear(string $nombre, int $instructorId, string $diaSemana, string $horaInicio, string $horaFin, int $cupoMaximo): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO clases (nombre, instructor_id, dia_semana, hora_inicio, hora_fin, cupo_maximo, cupos_disponibles)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([$nombre, $instructorId, $diaSemana, $horaInicio, $horaFin, $cupoMaximo, $cupoMaximo]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizar(
        int $id,
        string $nombre,
        int $instructorId,
        string $diaSemana,
        string $horaInicio,
        string $horaFin,
        int $cupoMaximo,
        int $activa = 1
    ): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE clases
             SET nombre = ?, instructor_id = ?, dia_semana = ?, hora_inicio = ?, hora_fin = ?,
                 cupos_disponibles = GREATEST(0, ? - (cupo_maximo - cupos_disponibles)),
                 cupo_maximo = ?, activa = ?
             WHERE id = ?'
        );

        return $stmt->execute([
            $nombre,
            $instructorId,
            $diaSemana,
            $horaInicio,
            $horaFin,
            $cupoMaximo,
            $cupoMaximo,
            $activa ? 1 : 0,
            $id,
        ]);
    }

    public function cancelar(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE clases SET activa = 0 WHERE id = ?'
        );

        return $stmt->execute([$id]);
    }

    public function eliminar(int $id): bool
    {
        return $this->cancelar($id);
    }

    public function estadisticas(?int $demoDatasetId = null): array
    {
        $sql =
            'SELECT
                COUNT(*) AS clases_totales,
                SUM(c.activa = 1) AS clases_activas,
                COALESCE(SUM(CASE WHEN c.activa = 1 THEN c.cupo_maximo ELSE 0 END), 0) AS cupos_totales,
                COALESCE(SUM(CASE WHEN c.activa = 1 THEN c.cupos_disponibles ELSE 0 END), 0) AS cupos_disponibles
             FROM clases c
             WHERE c.is_demo = ?';
        $params = [$demoDatasetId === null ? 0 : 1];
        if ($demoDatasetId !== null) {
            $sql .= ' AND c.demo_dataset_id = ?';
            $params[] = $demoDatasetId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $datos = $stmt->fetch() ?: [];
        $cuposTotales = (int) ($datos['cupos_totales'] ?? 0);
        $cuposDisponibles = (int) ($datos['cupos_disponibles'] ?? 0);

        return [
            'clases_totales' => (int) ($datos['clases_totales'] ?? 0),
            'clases_activas' => (int) ($datos['clases_activas'] ?? 0),
            'cupos_totales' => $cuposTotales,
            'cupos_disponibles' => $cuposDisponibles,
            'ocupacion_porcentaje' => $cuposTotales > 0
                ? round((($cuposTotales - $cuposDisponibles) / $cuposTotales) * 100, 1)
                : 0.0,
        ];
    }

    public function buscarPorId(int $id, ?int $demoDatasetId = null): array|false
    {
        $sql =
            'SELECT id, nombre, instructor_id, dia_semana, hora_inicio, hora_fin, cupo_maximo, cupos_disponibles, activa
             FROM clases WHERE id = ? AND is_demo = ?';
        $params = [$id, $demoDatasetId === null ? 0 : 1];
        if ($demoDatasetId !== null) {
            $sql .= ' AND demo_dataset_id = ?';
            $params[] = $demoDatasetId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch();
    }

    public function listarInscriptos(int $claseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id AS reserva_id, u.id AS usuario_id, u.nombre AS usuario_nombre, u.email AS usuario_email,
                    r.fecha_reserva, r.estado
             FROM reservas r
             JOIN usuarios u ON r.usuario_id = u.id
             WHERE r.clase_id = ?
             ORDER BY r.fecha_reserva DESC'
        );

        $stmt->execute([$claseId]);

        return $stmt->fetchAll();
    }

    public function contarClasesHoy(?int $demoDatasetId = null): int
    {
        $dias = [1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sabado', 7 => 'domingo'];
        $diaHoy = $dias[(int) date('N')];

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM clases WHERE dia_semana = ? AND activa = 1 AND is_demo = ?'
        );
        $params = [$diaHoy, $demoDatasetId === null ? 0 : 1];
        if ($demoDatasetId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM clases
                 WHERE dia_semana = ? AND activa = 1 AND is_demo = 1 AND demo_dataset_id = ?'
            );
            $params = [$diaHoy, $demoDatasetId];
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
